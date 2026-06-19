<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use App\Models\ItemBorrowingLog;
use App\Models\User;
use App\Notifications\ItemBorrowingRequestedNotification;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
                    $source = $this->resolveResubmissionSource(
                        $validated['resubmitted_from_id'] ?? null,
                        $user,
                        $attachmentPath !== null,
                    );
                    $expandedItems = $this->period->expand($validated['items']);
                    $this->lockItemsAndValidateAvailability($expandedItems->all());

                    $borrowing = ItemBorrowing::create([
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                        'user_id' => $user->id,
                        'resubmitted_from_id' => $source?->id,
                        'status' => ItemBorrowing::STATUS_WAITING,
                        'attachment' => $attachmentPath ?: $source?->attachment,
                        'item_id' => null,
                        'quantity' => 0,
                        'borrow_date' => null,
                        'return_date' => null,
                    ]);

                    foreach ($expandedItems as $itemData) {
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
                        'description' => $source
                            ? "Peminjaman barang diajukan ulang dari pengajuan #{$source->id}."
                            : 'Peminjaman '.count($validated['items']).' kartu barang diajukan oleh pengguna.',
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

                    $existingItems = $lockedBorrowing->items()->get();
                    $expandedItems = $this->period->expand($validated['items']);
                    $this->lockItemsAndValidateAvailability($expandedItems->all(), $existingItems);

                    $wasRevisionRequested = $lockedBorrowing->status === ItemBorrowing::STATUS_NEEDS_REVISION;
                    $updateData = [
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                        'status' => ItemBorrowing::STATUS_WAITING,
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

                    $lockedBorrowing->items()->delete();

                    foreach ($expandedItems as $itemData) {
                        [$borrowDate, $returnDate] = $this->period->parse($itemData);
                        $lockedBorrowing->items()->create([
                            'item_id' => $itemData['item_id'],
                            'quantity' => $itemData['quantity'],
                            'borrow_date' => $borrowDate,
                            'return_date' => $returnDate,
                        ]);
                    }

                    ItemBorrowingLog::query()->create([
                        'item_borrowing_id' => $lockedBorrowing->id,
                        'user_id' => $user->id,
                        'action' => 'revised',
                        'description' => $wasRevisionRequested
                            ? 'Revisi peminjaman barang dikirim dan menunggu persetujuan ulang.'
                            : 'Peminjaman barang diperbarui oleh pengguna.',
                    ]);

                    $itemBorrowing->setRawAttributes($lockedBorrowing->getAttributes(), true);
                });
            },
        );

        if (
            $oldAttachmentPath
            && ! ItemBorrowing::query()->where('attachment', $oldAttachmentPath)->exists()
        ) {
            $this->fileStorage->delete($oldAttachmentPath);
        }
        $itemBorrowing->load(['user', 'items.item', 'singleItem']);
        $this->notifyAdmins($itemBorrowing);
    }

    private function lockItemsAndValidateAvailability(array $itemsData, mixed $existingItems = null): void
    {
        $scheduledItems = collect($itemsData)
            ->map(function (array $itemData): array {
                [$borrowDate, $returnDate] = $this->period->parse($itemData);

                return [
                    ...$itemData,
                    'parsed_borrow_date' => $borrowDate,
                    'parsed_return_date' => $returnDate,
                ];
            });

        $items = Item::query()
            ->whereIn('id', $scheduledItems->pluck('item_id')->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach ($scheduledItems->groupBy('item_id') as $itemId => $itemSchedules) {
            $item = $items->get((int) $itemId);
            if (! $item?->is_available) {
                foreach ($itemSchedules as $index => $itemData) {
                    $inputIndex = (int) ($itemData['input_index'] ?? $index);
                    $errors["items.{$inputIndex}.item_id"] = 'Barang sedang tidak tersedia untuk dipinjam.';
                }

                continue;
            }

            $exclude = $existingItems
                ? collect($existingItems)
                    ->where('item_id', (int) $itemId)
                    ->pluck('id')
                : null;
            $boundaries = $itemSchedules
                ->flatMap(fn (array $schedule): array => [
                    $schedule['parsed_borrow_date']->timestamp,
                    $schedule['parsed_return_date']->timestamp,
                ])
                ->unique()
                ->sort()
                ->values();

            for ($boundaryIndex = 0; $boundaryIndex < $boundaries->count() - 1; $boundaryIndex++) {
                $segmentStart = Carbon::createFromTimestampUTC($boundaries[$boundaryIndex]);
                $segmentEnd = Carbon::createFromTimestampUTC($boundaries[$boundaryIndex + 1]);
                $activeSchedules = $itemSchedules->filter(fn (array $schedule): bool => (
                    $schedule['parsed_borrow_date']->lt($segmentEnd)
                    && $schedule['parsed_return_date']->gt($segmentStart)
                ));

                if ($activeSchedules->isEmpty()) {
                    continue;
                }

                $combinedQuantity = $activeSchedules
                    ->sum(fn (array $schedule): int => (int) $schedule['quantity']);
                $availability = $this->availabilityService->getAvailability(
                    $item,
                    $segmentStart,
                    $segmentEnd,
                    $exclude,
                );

                if ($availability['remaining_quantity'] >= $combinedQuantity) {
                    continue;
                }

                foreach ($activeSchedules as $index => $schedule) {
                    $inputIndex = (int) ($schedule['input_index'] ?? $index);
                    $errors["items.{$inputIndex}.quantity"] = sprintf(
                        'Stok tidak mencukupi. Total kebutuhan pada jadwal yang bertumpuk: %d, sisa tersedia: %d.',
                        $combinedQuantity,
                        $availability['remaining_quantity'],
                    );
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function resolveResubmissionSource(
        ?int $sourceId,
        User $user,
        bool $hasNewAttachment,
    ): ?ItemBorrowing {
        if (! $sourceId) {
            return null;
        }

        $source = ItemBorrowing::query()
            ->lockForUpdate()
            ->findOrFail($sourceId);

        if (
            $source->user_id !== $user->id
            || $source->status !== ItemBorrowing::STATUS_REJECTED
        ) {
            throw ValidationException::withMessages([
                'resubmitted_from_id' => 'Pengajuan sumber tidak valid untuk diajukan ulang.',
            ]);
        }

        if (
            ! $hasNewAttachment
            && (
                ! $source->attachment
                || ! Storage::disk(PublicFileStorage::DISK)->exists($source->attachment)
            )
        ) {
            throw ValidationException::withMessages([
                'attachment' => 'Lampiran wajib diunggah untuk pengajuan ulang ini.',
            ]);
        }

        return $source;
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
