<?php

namespace App\DataTransferObjects;

use App\Models\OtpCode;

final class OtpVerificationResult
{
    private function __construct(
        public readonly bool $success,
        public readonly bool $locked,
        public readonly ?OtpCode $otp = null
    ) {
    }

    public static function success(OtpCode $otp): self
    {
        return new self(true, false, $otp);
    }

    public static function invalid(bool $locked = false): self
    {
        return new self(false, $locked);
    }
}
