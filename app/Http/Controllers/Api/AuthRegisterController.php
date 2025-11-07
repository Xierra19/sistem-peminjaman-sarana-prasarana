<?php

namespace App\Http\Controllers\Api;

use App\DataTransferObjects\OtpVerificationResult;
use App\Exceptions\Otp\DailyEmailQuotaExceededException;
use App\Exceptions\Otp\OtpRateLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterResendRequest;
use App\Http\Requests\Auth\RegisterStartRequest;
use App\Http\Requests\Auth\RegisterVerifyCodeRequest;
use App\Http\Requests\Auth\RegisterVerifyLinkRequest;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\CaptchaVerifier;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AuthRegisterController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly CaptchaVerifier $captchaVerifier
    ) {
    }

    public function start(RegisterStartRequest $request): JsonResponse
    {
        if (!$this->captchaCheck($request->input('captcha_token'), $request->ip())) {
            return $this->tooManyResponse(422);
        }

        $email = strtolower($request->input('email'));
        $existing = User::query()->where('email', $email)->first();
        if ($existing && $existing->email_verified_at) {
            return $this->genericResponse();
        }

        $meta = [
            'pending_user' => [
                'name' => $request->input('name'),
                'password_hash' => Hash::make($request->input('password')),
                'role' => 'user',
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 191),
        ];

        return $this->dispatchOtp($email, OtpCode::CONTEXT_REGISTRATION, $meta);
    }

    public function verifyCode(RegisterVerifyCodeRequest $request): JsonResponse
    {
        $result = $this->otpService->verifyCode(
            strtolower($request->input('email')),
            OtpCode::CONTEXT_REGISTRATION,
            $request->input('code')
        );

        if (!$result->success) {
            return $this->verificationFailedResponse($request->input('email'), $result);
        }

        $this->completeRegistration($result->otp);

        return response()->json(['success' => true]);
    }

    public function verifyLink(RegisterVerifyLinkRequest $request): JsonResponse
    {
        $result = $this->otpService->verifyToken(
            strtolower($request->input('email')),
            OtpCode::CONTEXT_REGISTRATION,
            $request->input('token')
        );

        if (!$result->success) {
            return $this->verificationFailedResponse($request->input('email'), $result);
        }

        $this->completeRegistration($result->otp);

        return response()->json(['success' => true]);
    }

    public function resend(RegisterResendRequest $request): JsonResponse
    {
        if (!$this->captchaCheck($request->input('captcha_token'), $request->ip())) {
            return $this->tooManyResponse(422);
        }

        $email = strtolower($request->input('email'));
        $latestMeta = $this->latestPendingMeta($email);

        if (!$latestMeta) {
            return $this->genericResponse();
        }

        $meta = array_merge($latestMeta, [
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 191),
        ]);

        return $this->dispatchOtp($email, OtpCode::CONTEXT_REGISTRATION, $meta);
    }

    private function completeRegistration(?OtpCode $otp): void
    {
        if (!$otp) {
            return;
        }

        $pending = Arr::get($otp->meta, 'pending_user');
        if (!$pending || empty($pending['password_hash'])) {
            return;
        }

        $user = User::query()->firstOrNew([
            'email' => $otp->identifier,
        ]);

        if ($user->exists && $user->email_verified_at) {
            return;
        }

        $user->name = $pending['name'] ?? $user->name ?? 'Pengguna';
        $user->password = $pending['password_hash'];
        $user->role = $user->role ?? ($pending['role'] ?? 'user');
        $user->email_verified_at = now();
        $user->save();
    }

    private function dispatchOtp(string $email, string $context, array $meta): JsonResponse
    {
        try {
            $this->otpService->issue($email, $context, $meta);
        } catch (OtpRateLimitException) {
            return $this->tooManyResponse();
        } catch (DailyEmailQuotaExceededException) {
            return $this->quotaResponse();
        }

        return $this->genericResponse();
    }

    private function genericResponse(): JsonResponse
    {
        return response()->json([
            'message' => __('Jika data valid, kami telah mengirim instruksi verifikasi.'),
        ]);
    }

    private function invalidResponse(int $status = 400): JsonResponse
    {
        return response()->json([
            'message' => __('Kode tidak valid atau sudah kedaluwarsa.'),
        ], $status);
    }

    private function tooManyResponse(int $status = 429): JsonResponse
    {
        return response()->json([
            'message' => __('Terlalu banyak percobaan. Coba lagi nanti.'),
        ], $status);
    }

    private function quotaResponse(): JsonResponse
    {
        return response()->json([
            'message' => __('Batas pengiriman harian tercapai. Coba lagi besok.'),
        ], 429);
    }

    private function verificationFailedResponse(string $email, OtpVerificationResult $result): JsonResponse
    {
        if ($result->locked) {
            return $this->tooManyResponse();
        }

        if ($this->isAlreadyVerified($email)) {
            return $this->invalidResponse(409);
        }

        return $this->invalidResponse();
    }

    private function isAlreadyVerified(string $email): bool
    {
        return User::query()
            ->where('email', strtolower($email))
            ->whereNotNull('email_verified_at')
            ->exists();
    }

    private function latestPendingMeta(string $email): ?array
    {
        $meta = OtpCode::query()
            ->where('identifier', $email)
            ->where('context', OtpCode::CONTEXT_REGISTRATION)
            ->orderByDesc('id')
            ->value('meta');

        $pending = Arr::get((array) $meta, 'pending_user');

        if (!$pending || empty($pending['password_hash'])) {
            return null;
        }

        return (array) $meta;
    }

    private function captchaCheck(?string $token, ?string $ip): bool
    {
        try {
            $this->captchaVerifier->assertValid($token, $ip);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
