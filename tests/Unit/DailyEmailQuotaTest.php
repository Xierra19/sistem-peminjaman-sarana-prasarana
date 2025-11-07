<?php

namespace Tests\Unit;

use App\Exceptions\Otp\DailyEmailQuotaExceededException;
use App\Services\DailyEmailQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyEmailQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_or_fail_respects_daily_cap(): void
    {
        for ($i = 0; $i < DailyEmailQuota::DAILY_CAP; $i++) {
            DailyEmailQuota::guardOrFail();
        }

        $this->assertDatabaseHas('email_quota_counters', [
            'day_date' => now()->toDateString(),
            'sent_count' => DailyEmailQuota::DAILY_CAP,
        ]);

        $this->expectException(DailyEmailQuotaExceededException::class);
        DailyEmailQuota::guardOrFail();
    }
}
