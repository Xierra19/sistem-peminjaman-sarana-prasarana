<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class BookingStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Booking $booking,
        protected string $status,
        protected ?string $notes = null,
        protected ?string $moderatorName = null,
    ) {
        $this->booking->loadMissing(['roomSchedules.room.building.campus', 'user']);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $status = Str::headline($this->status);

        // Ubah status ke bahasa Indonesia
        $statusIndo = match ($this->status) {
            'approved' => 'Disetujui',
            'needs_revision' => 'Perlu Direvisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
            default => $status
        };

        $mail = (new MailMessage)
            ->subject($statusIndo.' Booking: '.$booking->title)
            ->greeting('Halo '.$booking->user?->name.',');

        if ($this->status === 'cancelled') {
            $mail->line('Dengan menyesal kami informasikan bahwa booking ruangan Anda yang sebelumnya disetujui telah dibatalkan oleh admin karena terdapat kegiatan dengan prioritas lebih tinggi.');
        } elseif ($this->status === 'needs_revision') {
            $mail->line('Admin meminta perbaikan pada pengajuan booking ruangan Anda. Silakan buka detail pengajuan dan kirim revisi.');
        } else {
            $mail->line('Pengajuan booking ruangan Anda telah '.$statusIndo.'.');
        }

        foreach ($booking->roomSchedules as $schedule) {
            $location = collect([
                $schedule->room?->name,
                $schedule->room?->building?->name,
                $schedule->room?->building?->campus?->name,
            ])->filter()->join(' · ');

            $mail->line('Ruangan: '.$location.' | '.$schedule->schedule_summary);
        }

        if ($this->moderatorName) {
            $mail->line('Diproses oleh: '.$this->moderatorName);
        }

        if ($this->notes) {
            $mail->line('Catatan: '.$this->notes);
        }

        // Tambahkan nomor surat jika booking disetujui
        if ($this->status === 'approved' && $booking->letter_number) {
            $mail->line('Nomor Surat: '.$booking->letter_number);
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', config('app.name'));

        if ($fromAddress) {
            $mail->from($fromAddress, $fromName);
        }

        return $mail
            ->salutation("Hormat kami,\n".$fromName)
            ->action('Lihat Booking Anda', route('bookings.index'))
            ->line('Terima kasih telah menggunakan '.$fromName.'.');
    }
}
