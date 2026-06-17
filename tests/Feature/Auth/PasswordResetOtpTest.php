<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_otp_does_not_require_captcha_token_when_captcha_is_disabled(): void
    {
        config(['services.captcha.enabled' => false]);

        $response = $this->postJson(route('api.auth.password.forgot'), [
            'email' => 'missing@student.esaunggul.ac.id',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Jika data valid, kami telah mengirim instruksi verifikasi.');
    }

    public function test_forgot_password_otp_requires_captcha_token_when_captcha_is_enabled(): void
    {
        config(['services.captcha.enabled' => true]);

        $response = $this->postJson(route('api.auth.password.forgot'), [
            'email' => 'missing@student.esaunggul.ac.id',
        ]);

        $response->assertJsonValidationErrors('captcha_token');
    }
}
