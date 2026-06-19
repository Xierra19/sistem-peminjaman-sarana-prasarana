<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\ItemBorrowing;
use App\Policies\BookingPolicy;
use App\Policies\ItemBorrowingPolicy;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(ItemBorrowing::class, ItemBorrowingPolicy::class);

        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            $applicationName = config('mail.from.name', config('app.name'));

            return (new MailMessage)
                ->subject('Verifikasi Alamat Email Anda')
                ->greeting('Halo '.$notifiable->name.',')
                ->line('Klik tombol berikut untuk memverifikasi alamat email dan mengaktifkan akun Anda.')
                ->action('Verifikasi Email', $verificationUrl)
                ->line('Abaikan email ini jika Anda tidak membuat akun.')
                ->salutation("Hormat kami,\n".$applicationName);
        });

        Vite::prefetch(concurrency: 3);

        RateLimiter::for('otp-issue', function (Request $request) {
            $email = strtolower((string) $request->input('email'));
            $ip = $request->ip() ?? 'unknown';

            return [
                Limit::perMinute(3)->by("otp-issue:{$email}:{$ip}"),
                Limit::perMinutes(15, 10)->by("otp-issue-ip:{$ip}"),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $email = strtolower((string) $request->input('email'));
            $ip = $request->ip() ?? 'unknown';

            return [
                Limit::perMinute(6)->by("otp-verify:{$email}:{$ip}"),
                Limit::perHour(30)->by("otp-verify-ip:{$ip}"),
            ];
        });
    }
}
