<?php

namespace App\Console\Commands;

use App\Services\ExpirePendingBookings as ExpirePendingBookingsService;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';

    protected $description = 'Expire pending room bookings after their final borrowing day has ended';

    public function handle(ExpirePendingBookingsService $service): int
    {
        $expiredCount = $service->handle();

        $this->info("{$expiredCount} booking kedaluwarsa diperbarui.");

        return self::SUCCESS;
    }
}
