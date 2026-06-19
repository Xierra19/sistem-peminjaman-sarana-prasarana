<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ItemBorrowingPeriod
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function parse(array $itemData): array
    {
        $timezone = config('app.business_timezone');

        return [
            Carbon::createFromFormat(
                'Y-m-d H:i',
                $itemData['borrow_date'].' '.$itemData['borrow_time'],
                $timezone,
            )->utc(),
            Carbon::createFromFormat(
                'Y-m-d H:i',
                $itemData['return_date'].' '.$itemData['return_time'],
                $timezone,
            )->utc(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function expand(array $cards): Collection
    {
        return collect($cards)
            ->flatMap(function (array $card, int $index): Collection {
                return collect($card['dates'] ?? [])
                    ->unique()
                    ->sort()
                    ->values()
                    ->map(fn (string $date) => [
                        'input_index' => $index,
                        'item_id' => $card['item_id'],
                        'quantity' => $card['quantity'],
                        'borrow_date' => $date,
                        'borrow_time' => $card['start_time'],
                        'return_date' => $date,
                        'return_time' => $card['end_time'],
                    ]);
            })
            ->values();
    }
}
