<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendOtpJob;
use App\Models\EmailQuotaCounter;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.captcha.enabled' => false]);
    }

    public function test_register_start_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/register/start', $this->registerPayload());

        $response->assertOk();

        $this->assertDatabaseCount('otp_codes', 1);
        $this->assertDatabaseHas('email_quota_counters', [
            'day_date' => now()->toDateString(),
            'sent_count' => 1,
        ]);

        Queue::assertPushed(SendOtpJob::class, 1);
    }

    public function test_register_verify_code_success(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register/start', $this->registerPayload());

        $payload = $this->extractOtpPayload();

        $response = $this->postJson('/api/auth/register/verify-code', [
            'email' => 'tester@student.esaunggul.ac.id',
            'code' => $payload['code'],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(User::where('email', 'tester@student.esaunggul.ac.id')->whereNotNull('email_verified_at')->exists());
        $this->assertNotNull(OtpCode::first()?->consumed_at);
    }

    public function test_register_verify_link_success(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register/start', $this->registerPayload());

        $payload = $this->extractOtpPayload();

        $response = $this->postJson('/api/auth/register/verify-link', [
            'email' => 'tester@student.esaunggul.ac.id',
            'token' => $payload['token'],
        ]);

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_wrong_code_five_times_locks_otp(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register/start', $this->registerPayload());

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/register/verify-code', [
                'email' => 'tester@student.esaunggul.ac.id',
                'code' => '111111',
            ]);
        }

        $response->assertStatus(429);
        $otp = OtpCode::first();
        $this->assertSame(5, $otp->attempts);
        $this->assertTrue($otp->expires_at->isPast());
    }

    public function test_resend_register_enforces_cooldown(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register/start', $this->registerPayload());

        $response = $this->postJson('/api/auth/register/resend', [
            'email' => 'tester@student.esaunggul.ac.id',
            'captcha_token' => 'token',
        ]);

        $response->assertStatus(429);
        $this->assertSame(1, OtpCode::count());
    }

    public function test_resend_register_cap_per_24_hours(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register/start', $this->registerPayload());

        OtpCode::query()->update([
            'last_sent_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);

        $second = $this->postJson('/api/auth/register/resend', [
            'email' => 'tester@student.esaunggul.ac.id',
            'captcha_token' => 'token',
        ]);

        $second->assertOk();
        $this->assertSame(2, OtpCode::count());

        OtpCode::query()->latest('id')->first()->update([
            'last_sent_at' => now()->subMinutes(10),
        ]);

        $third = $this->postJson('/api/auth/register/resend', [
            'email' => 'tester@student.esaunggul.ac.id',
            'captcha_token' => 'token',
        ]);

        $third->assertStatus(429);
        $this->assertSame(2, OtpCode::count());
    }

    public function test_daily_email_quota_caps_after_300(): void
    {
        Queue::fake();

        EmailQuotaCounter::create([
            'day_date' => now()->toDateString(),
            'sent_count' => 300,
        ]);

        $response = $this->postJson('/api/auth/register/start', $this->registerPayload());

        $response->assertStatus(429);
        $this->assertSame(0, OtpCode::count());
        Queue::assertNothingPushed();
    }

    private function extractOtpPayload(): array
    {
        $payload = [];

        Queue::assertPushed(SendOtpJob::class, function (SendOtpJob $job) use (&$payload) {
            $payload = json_decode(Crypt::decryptString($job->payloadCiphertext), true);

            return true;
        });

        return $payload;
    }

    private function registerPayload(): array
    {
        return [
            'name' => 'Tester',
            'email' => 'tester@student.esaunggul.ac.id',
            'password' => 'Password123!',
            'captcha_token' => 'token',
        ];
    }
}
