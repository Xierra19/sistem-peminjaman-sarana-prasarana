<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class BookingRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Booking $booking)
    {
        $this->booking->loadMissing(['user', 'roomSchedules.room.building.campus']);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $mail = (new MailMessage())
            ->subject('Pengajuan Booking Ruangan Baru: '.$booking->title)
            ->greeting('Halo Admin,')
            ->line('Terdapat pengajuan booking ruangan baru yang memerlukan tinjauan Anda.')
            ->line('Pemohon: '.$booking->user?->name.' ('.$booking->user?->email.')');

        foreach ($booking->roomSchedules as $schedule) {
            $location = collect([
                $schedule->room?->name,
                $schedule->room?->building?->name,
                $schedule->room?->building?->campus?->name,
            ])->filter()->join(' · ');

            $mail->line('Ruangan: '.$location.' | '.$schedule->schedule_summary);
        }

        if ($booking->description) {
            $mail->line('Keperluan: '.Str::limit($booking->description, 200));
        }

         $fromAddress = config('mail.from.address');
         $fromName = 'Sistem Peminjaman Sarana dan Prasarana';

         if ($fromAddress) {
             $mail->from($fromAddress, $fromName);
         }

         return $mail
             ->salutation("Hormat kami,\nTim Sistem Peminjaman Sarana dan Prasarana")
             ->action('Tinjau Booking', route('admin.bookings.show', $booking))
             ->line('Terima kasih telah mengelola jadwal kampus dengan baik.');
    }
}
