<?php

namespace App\Console\Commands;

use App\Models\EmailQuotaCounter;
use App\Models\OtpCode;
use Illuminate\Console\Command;

class RefreshOtpData extends Command
{
    protected $signature = 'otp:refresh
        {email? : Target email address}
        {--context=registration : OTP context to filter}
        {--quota : Reset daily email quota counters as well}';

    protected $description = 'Force-delete OTP records (and optionally quota counters) to bypass throttling while testing.';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email')) ?: null;
        $context = $this->option('context');

        $query = OtpCode::query()->when($email, function ($innerQuery) use ($email) {
            $innerQuery->where('identifier', $email);
        })->when($context, function ($innerQuery) use ($context) {
            $innerQuery->where('context', $context);
        });

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('No OTP rows matched the given criteria.');
        } else {
            $deleted = (clone $query)->delete();
            $this->info("Deleted {$deleted} OTP row(s).");
        }

        if ($this->option('quota')) {
            EmailQuotaCounter::query()->delete();
            $this->info('Email quota counters cleared.');
        }

        return self::SUCCESS;
    }
}
