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
        $this->booking->loadMissing(['room.building.campus', 'user']);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $status = Str::headline($this->status);

        $room = $booking->room;
        $building = $room?->building;
        $campus = $building?->campus;

        // Ubah status ke bahasa Indonesia
        $statusIndo = match($this->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
            default => $status
        };

        $mail = (new MailMessage())
            ->subject($statusIndo.' Booking: '.$booking->title)
            ->greeting('Halo '.$booking->user?->name.',');

        if ($this->status === 'cancelled') {
            $mail->line('Dengan menyesal kami informasikan bahwa booking ruangan Anda yang sebelumnya disetujui telah dibatalkan oleh admin karena terdapat kegiatan dengan prioritas lebih tinggi.');
        } else {
            $mail->line('Pengajuan booking ruangan Anda telah '.$statusIndo.'.');
        }

        if ($room?->name) {
            $location = $room->name;

            if ($building?->name) {
                $location .= ' · '.$building->name;
            }

            if ($campus?->name) {
                $location .= ' · '.$campus->name;
            }

            $mail->line('Ruangan: '.$location);
        }

        if ($booking->schedule_summary) {
            $mail->line('Jadwal: '.$booking->schedule_summary);
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
