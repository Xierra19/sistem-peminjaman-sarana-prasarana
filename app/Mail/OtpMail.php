<?php

namespace App\Mail;

use App\Models\OtpCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\CarbonInterface;

class OtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $context,
        public readonly string $code,
        public readonly string $token,
        public readonly CarbonInterface $expiresAt
    ) {
    }

    public function build(): self
    {
        $subject = $this->context === OtpCode::CONTEXT_REGISTRATION
            ? 'Verifikasi akun Anda'
            : 'Reset kata sandi Anda';

        $frontendBase = rtrim(config('app.frontend_url', config('app.url')), '/');
        $path = $this->context === OtpCode::CONTEXT_REGISTRATION
            ? '/auth/register/verify'
            : '/auth/password/verify';

        $magicLink = sprintf(
            '%s%s?email=%s&token=%s&context=%s',
            $frontendBase,
            $path,
            urlencode($this->email),
            urlencode($this->token),
            $this->context
        );

        return $this->subject($subject)
            ->view('emails.otp')
            ->with([
                'code' => $this->code,
                'magicLink' => $magicLink,
                'expiresAt' => $this->expiresAt,
                'context' => $this->context,
            ]);
    }
}
