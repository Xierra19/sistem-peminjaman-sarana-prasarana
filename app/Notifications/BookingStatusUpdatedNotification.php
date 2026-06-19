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
        $statusLabel = match ($this->status) {
            Booking::STATUS_APPROVED => 'Disetujui',
            Booking::STATUS_NEEDS_REVISION => 'Perlu Direvisi',
            Booking::STATUS_REJECTED => 'Ditolak',
            Booking::STATUS_CANCELLED => 'Dibatalkan',
            Booking::STATUS_EXPIRED => 'Kedaluwarsa',
            default => Str::headline($this->status),
        };

        $mail = (new MailMessage)
            ->subject($statusLabel.' Peminjaman Ruangan: '.$booking->title)
            ->greeting('Halo '.$booking->user?->name.',');

        if ($this->status === Booking::STATUS_CANCELLED) {
            $mail->line('Peminjaman ruangan Anda telah dibatalkan oleh admin.');
        } elseif ($this->status === Booking::STATUS_NEEDS_REVISION) {
            $mail->line('Tim BAP meminta perbaikan pada pengajuan peminjaman ruangan Anda. Silakan buka detail pengajuan dan kirim revisi.');
        } else {
            $mail->line('Status pengajuan peminjaman ruangan Anda: '.Str::lower($statusLabel).'.');
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
            ->action('Lihat Detail Pengajuan', route('bookings.show', $booking))
            ->line('Terima kasih telah menggunakan '.$fromName.'.');
    }
}
