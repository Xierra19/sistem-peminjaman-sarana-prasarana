<?php

namespace App\Exceptions\Otp;

use RuntimeException;

class OtpRateLimitException extends RuntimeException
{
    public function __construct(
        public readonly string $reason = 'limit',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($reason, $code, $previous);
    }
}
