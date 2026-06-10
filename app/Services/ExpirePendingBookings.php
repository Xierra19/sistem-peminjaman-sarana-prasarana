<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LogHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookings
{
    public const TIMEZONE = 'Asia/Jakarta';

    public function handle(?CarbonInterface $now = null): int
    {
        $today = ($now ?? now(self::TIMEZONE))
            ->copy()
            ->setTimezone(self::TIMEZONE)
            ->toDateString();
        $expiredCount = 0;

        Booking::query()
            ->whereIn('status', ['waiting', 'pending', 'requested'])
            ->where(function ($query) use ($today): void {
                $query
                    ->whereDate('schedule_end_date', '<', $today)
                    ->orWhere(function ($legacyQuery) use ($today): void {
                        $legacyQuery
                            ->whereNull('schedule_end_date')
                            ->whereDate('end_time', '<', $today);
                    });
            })
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$expiredCount, $now): void {
                foreach ($bookings as $booking) {
                    if ($this->expireIfPastDue($booking, $now)) {
                        $expiredCount++;
                    }
                }
            });

        return $expiredCount;
    }

    public function expireIfPastDue(Booking $booking, ?CarbonInterface $now = null): bool
    {
        return DB::transaction(function () use ($booking, $now): bool {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->find($booking->getKey());

            if (
                ! $lockedBooking
                || ! in_array($lockedBooking->status, ['waiting', 'pending', 'requested'], true)
                || ! $lockedBooking->isPastExpirationCutoff($now)
            ) {
                return false;
            }

            $lockedBooking->update([
                'status' => 'expired',
            ]);

            LogHistory::query()->create([
                'booking_id' => $lockedBooking->id,
                'user_id' => null,
                'action' => 'expired',
                'description' => 'Permintaan kedaluwarsa karena belum diproses hingga hari peminjaman terakhir berakhir.',
            ]);

            $booking->setRawAttributes($lockedBooking->getAttributes(), true);

            return true;
        });
    }
}
