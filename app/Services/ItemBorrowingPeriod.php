<?php

namespace App\Services;

use Carbon\Carbon;

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
}
