<?php

namespace App\Services;

use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingLog;
use App\Models\User;
use App\Support\CancellationResult;
use Illuminate\Support\Facades\DB;

final class ItemBorrowingCancellationService
{
    public function cancel(ItemBorrowing $itemBorrowing, User $user): CancellationResult
    {
        return DB::transaction(function () use ($itemBorrowing, $user): CancellationResult {
            $lockedBorrowing = ItemBorrowing::query()
                ->lockForUpdate()
                ->findOrFail($itemBorrowing->getKey());

            $result = match (true) {
                $lockedBorrowing->status === ItemBorrowing::STATUS_CANCELLED => CancellationResult::AlreadyCancelled,
                ! in_array($lockedBorrowing->status, ItemBorrowing::CANCELLABLE_STATUSES, true) => CancellationResult::NotAllowed,
                default => null,
            };

            if ($result) {
                $itemBorrowing->setRawAttributes($lockedBorrowing->getAttributes(), true);

                return $result;
            }

            $lockedBorrowing->update([
                'status' => ItemBorrowing::STATUS_CANCELLED,
            ]);

            ItemBorrowingLog::query()->create([
                'item_borrowing_id' => $lockedBorrowing->id,
                'user_id' => $user->id,
                'action' => ItemBorrowing::STATUS_CANCELLED,
                'description' => 'Peminjaman barang dibatalkan oleh pemohon.',
            ]);

            $itemBorrowing->setRawAttributes($lockedBorrowing->getAttributes(), true);

            return CancellationResult::Cancelled;
        });
    }
}
