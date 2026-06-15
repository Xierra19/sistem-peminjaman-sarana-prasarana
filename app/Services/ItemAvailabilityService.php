<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ItemAvailabilityService
{
    public function getAvailabilityForUser(
        Item $item,
        Carbon $borrowDate,
        Carbon $returnDate,
        ?int $excludeItemBorrowingItemId,
        int $userId,
    ): array {
        $excludeRecord = $excludeItemBorrowingItemId
            ? ItemBorrowingItem::query()
                ->whereKey($excludeItemBorrowingItemId)
                ->where('item_id', $item->id)
                ->whereHas('borrowing', function ($query) use ($userId): void {
                    $query->where('user_id', $userId)
                        ->whereIn('status', ItemBorrowing::EDITABLE_STATUSES);
                })
                ->first()
            : null;

        return $this->getAvailability($item, $borrowDate, $returnDate, $excludeRecord);
    }

    /**
     * Get availability for item - support both legacy ItemBorrowing & new ItemBorrowingItem
     */
    public function getAvailability(
        Item $item,
        Carbon $borrowDate,
        Carbon $returnDate,
        mixed $excludeRecord = null
    ): array {
        $overlaps = $this->overlappingRecords($item, $borrowDate, $returnDate, $excludeRecord);
        $reservedQuantity = $overlaps->sum('quantity');
        $totalQuantity = (int) $item->quantity;
        $remainingQuantity = max(0, $totalQuantity - $reservedQuantity);

        return [
            'available' => (bool) $item->is_available && $remainingQuantity > 0,
            'total_quantity' => $totalQuantity,
            'reserved_quantity' => (int) $reservedQuantity,
            'remaining_quantity' => $remainingQuantity,
            'borrowings' => $overlaps->map(function ($record) {
                // Pastikan tanggal tidak null dan format dengan benar
                $borrowDate = $record->borrow_date instanceof Carbon
                    ? $record->borrow_date
                    : ($record->borrow_date ? Carbon::parse($record->borrow_date) : null);

                $returnDate = $record->return_date instanceof Carbon
                    ? $record->return_date
                    : ($record->return_date ? Carbon::parse($record->return_date) : null);

                return [
                    'id' => $record->id,
                    'title' => $record->borrowing?->title ?? $record->title ?? 'Unknown',
                    'status' => $record->borrowing?->status ?? $record->status ?? 'unknown',
                    'quantity' => $record->quantity,
                    'borrow_date' => $borrowDate?->toIso8601String(),
                    'return_date' => $returnDate?->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    public function hasEnoughStock(
        Item $item,
        Carbon $borrowDate,
        Carbon $returnDate,
        int $requestedQuantity,
        mixed $excludeRecord = null
    ): bool {
        $availability = $this->getAvailability($item, $borrowDate, $returnDate, $excludeRecord);

        return $availability['remaining_quantity'] >= $requestedQuantity;
    }

    /**
     * Get overlapping records (supports both legacy & pivot)
     */
    public function overlappingRecords(Item $item, Carbon $borrowDate, Carbon $returnDate, mixed $excludeRecord = null): Collection
    {
        $legacyQuery = ItemBorrowing::overlappingPeriod($item->id, $borrowDate, $returnDate);

        $pivotQuery = ItemBorrowingItem::where('item_id', $item->id)
            ->where('borrow_date', '<', $returnDate)
            ->where('return_date', '>', $borrowDate)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('item_borrowings')
                    ->whereColumn('item_borrowings.id', 'item_borrowing_items.item_borrowing_id')
                  ->whereNotIn('item_borrowings.status', ItemBorrowing::NON_BLOCKING_STATUSES);
            });

        // Exclude logic
        if ($excludeRecord) {
            if ($excludeRecord instanceof ItemBorrowing) {
                $legacyQuery->whereKeyNot($excludeRecord->id);
            } elseif (method_exists($excludeRecord, 'borrowing') || method_exists($excludeRecord, 'borrowing_id')) {
                $pivotQuery->where('id', '!=', $excludeRecord->id);
            }
        }

        $legacyBorrowings = $legacyQuery->with('user:id,name,email')->get();
        $pivotItems = $pivotQuery->get();

        return $legacyBorrowings->merge($pivotItems);
    }
}
