<?php

namespace App\Services;

use App\Exceptions\Otp\DailyEmailQuotaExceededException;
use App\Models\EmailQuotaCounter;
use Illuminate\Support\Facades\DB;

final class DailyEmailQuota
{
    public const DAILY_CAP = 300;

    /**
     * Ensures the daily email quota still has room and increments atomically.
     *
     * @throws DailyEmailQuotaExceededException
     */
    public static function guardOrFail(): void
    {
        DB::transaction(function (): void {
            $row = EmailQuotaCounter::firstOrCreate(
                ['day_date' => now()->toDateString()],
                ['sent_count' => 0]
            );

            $fresh = EmailQuotaCounter::whereKey($row->id)->lockForUpdate()->first();

            if ($fresh->sent_count >= self::DAILY_CAP) {
                throw new DailyEmailQuotaExceededException('DAILY_EMAIL_QUOTA_EXCEEDED');
            }

            $fresh->increment('sent_count');
        }, 3);
    }
}
