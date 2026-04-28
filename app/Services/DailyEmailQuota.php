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
            $today = now()->toDateString();
            $timestamp = now();

            EmailQuotaCounter::query()->insertOrIgnore([
                'day_date' => $today,
                'sent_count' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $fresh = EmailQuotaCounter::query()
                ->whereDate('day_date', $today)
                ->lockForUpdate()
                ->first();

            if ($fresh->sent_count >= self::DAILY_CAP) {
                throw new DailyEmailQuotaExceededException('DAILY_EMAIL_QUOTA_EXCEEDED');
            }

            $fresh->increment('sent_count');
        }, 3);
    }
}
