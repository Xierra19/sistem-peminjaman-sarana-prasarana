<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_request_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_verify_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password/verify?email=user@example.com');

        $response->assertStatus(200);
    }

    public function test_reset_password_otp_can_be_requested(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->postJson(route('api.auth.password.forgot'), [
            'email' => $user->email,
        ])->assertOk();

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo($user->email));
    }

    public function test_password_can_be_reset_with_valid_otp_code(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $otpCode = null;

        $this->postJson(route('api.auth.password.forgot'), [
            'email' => $user->email,
        ])->assertOk();

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($user, &$otpCode): bool {
            $otpCode = $mail->code;

            return $mail->hasTo($user->email);
        });

        $this->assertNotNull($otpCode);

        $this->postJson(route('api.auth.password.reset-code'), [
            'email' => $user->email,
            'code' => $otpCode,
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
