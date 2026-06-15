<?php

namespace App\Services;

use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingLog;
use App\Models\User;
use App\Notifications\ItemBorrowingStatusUpdatedNotification;
use App\Support\AdminStatusTransitionResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ItemBorrowingApprovalWorkflow
{
    public function __construct(
        private readonly PublicFileStorage $fileStorage,
    ) {}

    public function update(
        ItemBorrowing $itemBorrowing,
        string $targetStatus,
        ?string $notes,
        ?UploadedFile $signedLetter,
        User $admin,
    ): AdminStatusTransitionResult {
        $oldSignedLetterPath = null;
        $newSignedLetterPath = null;

        $result = $this->fileStorage->runWithStoredFile(
            $signedLetter,
            'item-borrowing-signed-letters',
            function (?string $storedPath) use (
                $itemBorrowing,
                $targetStatus,
                $notes,
                $admin,
                &$oldSignedLetterPath,
                &$newSignedLetterPath,
            ): AdminStatusTransitionResult {
                $newSignedLetterPath = $storedPath;

                return DB::transaction(function () use (
                    $itemBorrowing,
                    $targetStatus,
                    $notes,
                    $admin,
                    $storedPath,
                    &$oldSignedLetterPath,
                ): AdminStatusTransitionResult {
                    $lockedBorrowing = ItemBorrowing::query()
                        ->with('items')
                        ->lockForUpdate()
                        ->findOrFail($itemBorrowing->getKey());
                    $currentStatus = $lockedBorrowing->status === ItemBorrowing::STATUS_REQUESTED
                        ? ItemBorrowing::STATUS_WAITING
                        : $lockedBorrowing->status;

                    if (in_array($currentStatus, ItemBorrowing::FINAL_STATUSES, true)) {
                        return AdminStatusTransitionResult::Final;
                    }

                    if (
                        in_array($targetStatus, [
                            ItemBorrowing::STATUS_APPROVED,
                            ItemBorrowing::STATUS_REJECTED,
                        ], true)
                        && $currentStatus !== ItemBorrowing::STATUS_WAITING
                    ) {
                        return AdminStatusTransitionResult::PendingRequired;
                    }

                    if (
                        $targetStatus === ItemBorrowing::STATUS_CANCELLED
                        && $currentStatus !== ItemBorrowing::STATUS_APPROVED
                    ) {
                        return AdminStatusTransitionResult::ApprovedRequired;
                    }

                    if (
                        $targetStatus === ItemBorrowing::STATUS_CANCELLED
                        && $lockedBorrowing->effective_status === ItemBorrowing::STATUS_COMPLETED
                    ) {
                        return AdminStatusTransitionResult::Completed;
                    }

                    $updatePayload = [
                        'status' => $targetStatus,
                    ];

                    if ($storedPath) {
                        $oldSignedLetterPath = $lockedBorrowing->signed_letter;
                        $updatePayload['signed_letter'] = $storedPath;
                        $updatePayload['signed_letter_uploaded_at'] = now();
                    }

                    if ($targetStatus === ItemBorrowing::STATUS_APPROVED) {
                        $updatePayload['approved_at'] = now();
                        $updatePayload['approved_by'] = $admin->id;
                    }

                    $lockedBorrowing->update($updatePayload);

                    $description = match ($targetStatus) {
                        ItemBorrowing::STATUS_APPROVED => 'Peminjaman barang disetujui',
                        ItemBorrowing::STATUS_REJECTED => 'Peminjaman barang ditolak',
                        ItemBorrowing::STATUS_CANCELLED => 'Peminjaman barang dibatalkan oleh admin',
                        default => 'Status peminjaman barang diperbarui',
                    };

                    if ($notes) {
                        $description .= ' - '.$notes;
                    }

                    ItemBorrowingLog::query()->create([
                        'item_borrowing_id' => $lockedBorrowing->id,
                        'user_id' => $admin->id,
                        'action' => $targetStatus,
                        'description' => $description,
                    ]);

                    $itemBorrowing->setRawAttributes($lockedBorrowing->getAttributes(), true);

                    return AdminStatusTransitionResult::Updated;
                });
            },
        );

        if ($result !== AdminStatusTransitionResult::Updated) {
            $this->fileStorage->delete($newSignedLetterPath);

            return $result;
        }

        $this->fileStorage->delete($oldSignedLetterPath);
        $this->notifyOwner($itemBorrowing, $targetStatus, $notes, $admin);

        return $result;
    }

    private function notifyOwner(
        ItemBorrowing $itemBorrowing,
        string $targetStatus,
        ?string $notes,
        User $admin,
    ): void {
        $itemBorrowing->load(['user', 'items.item', 'singleItem']);

        if (! $itemBorrowing->user || empty($itemBorrowing->user->email)) {
            return;
        }

        try {
            $itemBorrowing->user->notify(new ItemBorrowingStatusUpdatedNotification(
                $itemBorrowing,
                $targetStatus,
                $notes,
                $admin->name,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
