<?php

namespace App\Services;

use App\DataTransferObjects\OtpVerificationResult;
use App\Exceptions\Otp\DailyEmailQuotaExceededException;
use App\Exceptions\Otp\OtpRateLimitException;
use App\Jobs\SendOtpJob;
use App\Models\OtpCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class OtpService
{
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    /**
     * @throws OtpRateLimitException
     * @throws DailyEmailQuotaExceededException
     */
    public function issue(string $email, string $context, array $meta = []): void
    {
        $this->ensureRequestLimit($email, $context);

        DailyEmailQuota::guardOrFail();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(16));
        $now = Carbon::now();
        $expiresAt = $now->clone()->addMinutes(self::TTL_MINUTES);

        DB::transaction(function () use ($email, $context, $code, $token, $expiresAt, $now, $meta): void {
            $this->revokeActiveWithoutConsumption($email, $context);

            OtpCode::create([
                'identifier' => $email,
                'context' => $context,
                'channel' => OtpCode::CHANNEL_EMAIL,
                'code_hash' => Hash::make($code),
                'token_hash' => Hash::make($token),
                'attempts' => 0,
                'max_attempts' => self::MAX_ATTEMPTS,
                'send_count' => 1,
                'last_sent_at' => $now,
                'expires_at' => $expiresAt,
                'meta' => $meta,
            ]);
        });

        $payload = Crypt::encryptString(json_encode([
            'code' => $code,
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        SendOtpJob::dispatch($email, $context, $payload);
    }

    public function verifyCode(string $email, string $context, string $candidate): OtpVerificationResult
    {
        $otp = $this->findActive($email, $context);
        if (!$otp) {
            return OtpVerificationResult::invalid();
        }

        if (!Hash::check($candidate, $otp->code_hash)) {
            $locked = $this->registerFailedAttempt($otp);

            return OtpVerificationResult::invalid($locked);
        }

        $consumed = $this->consumeAndRevokeSiblings($otp);

        return OtpVerificationResult::success($consumed);
    }

    public function verifyToken(string $email, string $context, string $token): OtpVerificationResult
    {
        $otp = $this->findActive($email, $context);
        if (!$otp) {
            return OtpVerificationResult::invalid();
        }

        if (!Hash::check($token, (string) $otp->token_hash)) {
            $locked = $this->registerFailedAttempt($otp);

            return OtpVerificationResult::invalid($locked);
        }

        $consumed = $this->consumeAndRevokeSiblings($otp);

        return OtpVerificationResult::success($consumed);
    }

    private function consumeAndRevokeSiblings(OtpCode $otp): OtpCode
    {
        return DB::transaction(function () use ($otp): OtpCode {
            $otp->fill([
                'consumed_at' => Carbon::now(),
            ])->save();

            OtpCode::query()
                ->where('identifier', $otp->identifier)
                ->where('context', $otp->context)
                ->whereNull('consumed_at')
                ->where('id', '!=', $otp->id)
                ->update(['expires_at' => Carbon::now()]);

            return $otp->fresh();
        });
    }

    private function revokeActiveWithoutConsumption(string $email, string $context): void
    {
        OtpCode::query()
            ->where('identifier', $email)
            ->where('context', $context)
            ->whereNull('consumed_at')
            ->update(['expires_at' => Carbon::now()]);
    }

    private function registerFailedAttempt(OtpCode $otp): bool
    {
        $attempts = $otp->attempts + 1;
        $otp->forceFill(['attempts' => $attempts])->save();

        if ($attempts >= ($otp->max_attempts ?? self::MAX_ATTEMPTS)) {
            $otp->forceFill(['expires_at' => Carbon::now()])->save();

            return true;
        }

        return false;
    }

    private function findActive(string $email, string $context): ?OtpCode
    {
        return OtpCode::query()
            ->where('identifier', $email)
            ->where('context', $context)
            ->active()
            ->latest('id')
            ->first();
    }

    /**
     * @throws OtpRateLimitException
     */
    private function ensureRequestLimit(string $email, string $context): void
    {
        $cooldownMinutes = $context === OtpCode::CONTEXT_REGISTRATION ? 5 : 15;
        $limitPerDay = $context === OtpCode::CONTEXT_REGISTRATION ? 2 : 3;

        $recent = OtpCode::query()
            ->where('identifier', $email)
            ->where('context', $context)
            ->where('last_sent_at', '>=', Carbon::now()->subMinutes($cooldownMinutes))
            ->exists();

        if ($recent) {
            throw new OtpRateLimitException('COOLDOWN');
        }

        $countLast24Hours = OtpCode::query()
            ->where('identifier', $email)
            ->where('context', $context)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        if ($countLast24Hours >= $limitPerDay) {
            throw new OtpRateLimitException('DAILY_LIMIT');
        }
    }
}
