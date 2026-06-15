<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use App\Models\ItemBorrowingLog;
use App\Models\User;
use App\Notifications\ItemBorrowingRequestedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ItemBorrowingWorkflow
{
    public function __construct(
        private readonly ItemAvailabilityService $availabilityService,
        private readonly ItemBorrowingPeriod $period,
        private readonly PublicFileStorage $fileStorage,
    ) {}

    public function create(array $validated, ?UploadedFile $attachment, User $user): ItemBorrowing
    {
        $itemBorrowing = $this->fileStorage->runWithStoredFile(
            $attachment,
            'item-borrowing-attachments',
            function (?string $attachmentPath) use ($validated, $user): ItemBorrowing {
                return DB::transaction(function () use ($validated, $user, $attachmentPath): ItemBorrowing {
                    $this->lockItemsAndValidateAvailability($validated['items']);

                    $borrowing = ItemBorrowing::create([
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                        'attachment' => $attachmentPath,
                        'user_id' => $user->id,
                        'status' => ItemBorrowing::STATUS_WAITING,
                        'item_id' => null,
                        'quantity' => 0,
                        'borrow_date' => null,
                        'return_date' => null,
                    ]);

                    foreach (collect($validated['items'])->keyBy('item_id')->values() as $itemData) {
                        [$borrowDate, $returnDate] = $this->period->parse($itemData);

                        ItemBorrowingItem::create([
                            'item_borrowing_id' => $borrowing->id,
                            'item_id' => $itemData['item_id'],
                            'quantity' => $itemData['quantity'],
                            'borrow_date' => $borrowDate,
                            'return_date' => $returnDate,
                        ]);
                    }

                    ItemBorrowingLog::create([
                        'item_borrowing_id' => $borrowing->id,
                        'user_id' => $user->id,
                        'action' => 'requested',
                        'description' => 'Peminjaman '.count($validated['items']).' jenis barang diajukan oleh pengguna.',
                    ]);

                    return $borrowing;
                });
            },
        );

        $itemBorrowing->load(['user', 'items.item']);
        $this->notifyAdmins($itemBorrowing);

        return $itemBorrowing;
    }

    public function update(
        array $validated,
        ItemBorrowing $itemBorrowing,
        ?UploadedFile $attachment,
        User $user,
    ): void {
        $oldAttachmentPath = null;

        $this->fileStorage->runWithStoredFile(
            $attachment,
            'item-borrowing-attachments',
            function (?string $newAttachmentPath) use (
                $validated,
                $itemBorrowing,
                $user,
                &$oldAttachmentPath,
            ): void {
                DB::transaction(function () use (
                    $validated,
                    $itemBorrowing,
                    $user,
                    $newAttachmentPath,
                    &$oldAttachmentPath,
                ): void {
                    $lockedBorrowing = ItemBorrowing::query()
                        ->whereKey($itemBorrowing->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    Gate::forUser($user)->authorize('update', $lockedBorrowing);

                    $existingItems = $lockedBorrowing->items()->get()->keyBy('id');
                    $this->lockItemsAndValidateAvailability($validated['items'], $existingItems);

                    $updateData = [
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                        'item_id' => null,
                        'quantity' => 0,
                        'borrow_date' => null,
                        'return_date' => null,
                    ];

                    if ($newAttachmentPath) {
                        $oldAttachmentPath = $lockedBorrowing->attachment;
                        $updateData['attachment'] = $newAttachmentPath;
                    }

                    $lockedBorrowing->update($updateData);

                    $existingIds = collect($validated['items'])->pluck('id')->filter()->all();
                    $lockedBorrowing->items()
                        ->whereNotIn('id', $existingIds)
                        ->delete();

                    foreach ($validated['items'] as $itemData) {
                        [$borrowDate, $returnDate] = $this->period->parse($itemData);
                        $itemValues = [
                            'item_id' => $itemData['item_id'],
                            'quantity' => $itemData['quantity'],
                            'borrow_date' => $borrowDate,
                            'return_date' => $returnDate,
                        ];

                        if (isset($itemData['id'])) {
                            $existingItems->get((int) $itemData['id'])->update($itemValues);

                            continue;
                        }

                        $lockedBorrowing->items()->create($itemValues);
                    }
                });
            },
        );

        $this->fileStorage->delete($oldAttachmentPath);
    }

    private function lockItemsAndValidateAvailability(array $itemsData, mixed $existingItems = null): void
    {
        $items = Item::query()
            ->whereIn('id', collect($itemsData)->pluck('item_id')->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach ($itemsData as $index => $itemData) {
            $item = $items->get((int) $itemData['item_id']);

            if (! $item?->is_available) {
                $errors["items.{$index}.item_id"] = 'Barang sedang tidak tersedia untuk dipinjam.';

                continue;
            }

            [$borrowDate, $returnDate] = $this->period->parse($itemData);
            $exclude = isset($itemData['id'])
                ? $existingItems?->get((int) $itemData['id'])
                : null;

            if ($this->availabilityService->hasEnoughStock(
                $item,
                $borrowDate,
                $returnDate,
                (int) $itemData['quantity'],
                $exclude,
            )) {
                continue;
            }

            $availability = $this->availabilityService->getAvailability(
                $item,
                $borrowDate,
                $returnDate,
                $exclude,
            );
            $errors["items.{$index}.quantity"] = 'Stok tidak mencukupi. Sisa tersedia: '.$availability['remaining_quantity'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function notifyAdmins(ItemBorrowing $itemBorrowing): void
    {
        $admins = User::query()
            ->where('role', User::ROLE_ADMIN_SARPRAS)
            ->whereNotNull('email')
            ->get();

        Log::info('ItemBorrowing: Preparing to send notification', [
            'admins_count' => $admins->count(),
            'admins_emails' => $admins->pluck('email')->all(),
            'item_borrowing_id' => $itemBorrowing->id,
        ]);

        if ($admins->isEmpty()) {
            Log::warning('ItemBorrowing: No admins found with valid email. Notification not sent.');

            return;
        }

        try {
            Log::info('ItemBorrowing: Sending notification now...');
            Notification::send($admins, new ItemBorrowingRequestedNotification($itemBorrowing));
            Log::info('ItemBorrowing: Notification sent successfully.');
        } catch (Throwable $exception) {
            Log::error('ItemBorrowing: Failed to send notification', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            report($exception);
        }
    }
}
