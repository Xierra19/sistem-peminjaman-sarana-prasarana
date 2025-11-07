<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class CaptchaVerifier
{
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (!config('services.captcha.enabled', false)) {
            return true;
        }

        $secret = config('services.captcha.secret');
        $verifyUrl = config('services.captcha.verify_url');

        if (!$secret || !$verifyUrl || !$token) {
            return false;
        }

        $response = Http::asForm()->post($verifyUrl, [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return (bool) $response->json('success', false);
    }

    /**
     * @throws RuntimeException
     */
    public function assertValid(?string $token, ?string $ip = null): void
    {
        if (!$this->verify($token, $ip)) {
            throw new RuntimeException('CAPTCHA_FAILED');
        }
    }
}
