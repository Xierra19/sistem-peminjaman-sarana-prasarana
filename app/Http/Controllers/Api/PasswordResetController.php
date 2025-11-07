<?php

namespace App\Http\Controllers\Api;

use App\DataTransferObjects\OtpVerificationResult;
use App\Exceptions\Otp\DailyEmailQuotaExceededException;
use App\Exceptions\Otp\OtpRateLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordByCodeRequest;
use App\Http\Requests\Auth\ResetPasswordByLinkRequest;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\CaptchaVerifier;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly CaptchaVerifier $captchaVerifier
    ) {
    }

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        if (!$this->captchaCheck($request->input('captcha_token'), $request->ip())) {
            return $this->tooManyResponse(422);
        }

        $email = strtolower($request->input('email'));
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            return $this->genericResponse();
        }

        $meta = [
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 191),
        ];

        return $this->dispatchOtp($email, OtpCode::CONTEXT_RESET_PASSWORD, $meta);
    }

    public function resetByCode(ResetPasswordByCodeRequest $request): JsonResponse
    {
        $result = $this->otpService->verifyCode(
            strtolower($request->input('email')),
            OtpCode::CONTEXT_RESET_PASSWORD,
            $request->input('code')
        );

        if (!$result->success) {
            return $this->verificationFailedResponse($result);
        }

        $this->applyPassword($request->input('email'), $request->input('new_password'));

        return response()->json(['success' => true]);
    }

    public function resetByLink(ResetPasswordByLinkRequest $request): JsonResponse
    {
        $result = $this->otpService->verifyToken(
            strtolower($request->input('email')),
            OtpCode::CONTEXT_RESET_PASSWORD,
            $request->input('token')
        );

        if (!$result->success) {
            return $this->verificationFailedResponse($result);
        }

        $this->applyPassword($request->input('email'), $request->input('new_password'));

        return response()->json(['success' => true]);
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

    private function applyPassword(string $email, string $newPassword): void
    {
        $user = User::query()->where('email', strtolower($email))->first();

        if (!$user) {
            return;
        }

        $user->password = Hash::make($newPassword);

        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
        }

        $user->save();
    }

    private function verificationFailedResponse(OtpVerificationResult $result): JsonResponse
    {
        if ($result->locked) {
            return $this->tooManyResponse();
        }

        return $this->invalidResponse();
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
