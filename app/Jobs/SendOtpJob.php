<?php

namespace App\Jobs;

use App\Mail\OtpMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use JsonException;

class SendOtpJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly string $email,
        public readonly string $context,
        public readonly string $payloadCiphertext
    ) {
    }

    /**
     * @throws JsonException
     */
    public function handle(): void
    {
        $data = json_decode(
            Crypt::decryptString($this->payloadCiphertext),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $expiresAt = Carbon::parse($data['expires_at'])->toImmutable();

        Mail::to($this->email)->send(new OtpMail(
            email: $this->email,
            context: $this->context,
            code: $data['code'],
            token: $data['token'],
            expiresAt: $expiresAt
        ));
    }
}
