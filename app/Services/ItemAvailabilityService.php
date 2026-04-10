<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemBorrowing;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ItemAvailabilityService
{
    /**
     * @return array{
     *     available: bool,
     *     total_quantity: int,
     *     reserved_quantity: int,
     *     remaining_quantity: int,
     *     borrowings: array<int, array<string, mixed>>
     * }
     */
    public function getAvailability(Item $item, Carbon $borrowDate, Carbon $returnDate, ?int $excludeBorrowingId = null): array
    {
        $borrowings = $this->overlappingBorrowings($item, $borrowDate, $returnDate, $excludeBorrowingId);
        $reservedQuantity = (int) $borrowings->sum('quantity');
        $totalQuantity = (int) $item->quantity;
        $remainingQuantity = max(0, $totalQuantity - $reservedQuantity);

        return [
            'available' => (bool) $item->is_available && $remainingQuantity > 0,
            'total_quantity' => $totalQuantity,
            'reserved_quantity' => $reservedQuantity,
            'remaining_quantity' => $remainingQuantity,
            'borrowings' => $borrowings->map(fn (ItemBorrowing $borrowing) => [
                'id' => $borrowing->id,
                'title' => $borrowing->title,
                'status' => $borrowing->status,
                'quantity' => $borrowing->quantity,
                'borrow_date' => optional($borrowing->borrow_date)->format('Y-m-d'),
                'return_date' => optional($borrowing->return_date)->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function hasEnoughStock(Item $item, Carbon $borrowDate, Carbon $returnDate, int $requestedQuantity, ?int $excludeBorrowingId = null): bool
    {
        $availability = $this->getAvailability($item, $borrowDate, $returnDate, $excludeBorrowingId);

        return $availability['remaining_quantity'] >= $requestedQuantity;
    }

    /**
     * @return Collection<int, ItemBorrowing>
     */
    public function overlappingBorrowings(Item $item, Carbon $borrowDate, Carbon $returnDate, ?int $excludeBorrowingId = null): Collection
    {
        $query = ItemBorrowing::query()
            ->with('user:id,name,email')
            ->overlappingPeriod($item->id, $borrowDate, $returnDate)
            ->orderBy('borrow_date');

        if ($excludeBorrowingId) {
            $query->whereKeyNot($excludeBorrowingId);
        }

        return $query->get();
    }
}
