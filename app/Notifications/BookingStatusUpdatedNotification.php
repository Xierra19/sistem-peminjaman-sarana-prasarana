<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
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

        $start = $booking->start_time ? Carbon::parse($booking->start_time) : null;
        $end = $booking->end_time ? Carbon::parse($booking->end_time) : null;

        $mail = (new MailMessage())
            ->subject($status.' Booking: '.$booking->title)
            ->greeting('Hello '.$booking->user?->name.',')
            ->line('Your room booking request has been '.$status.'.');

        if ($room?->name) {
            $location = $room->name;

            if ($building?->name) {
                $location .= ' · '.$building->name;
            }

            if ($campus?->name) {
                $location .= ' · '.$campus->name;
            }

            $mail->line('Room: '.$location);
        }

        if ($start && $end) {
            $mail->line('Schedule: '.$start->format('d M Y H:i').' - '.$end->format('d M Y H:i'));
        }

        if ($this->moderatorName) {
            $mail->line('Reviewed by: '.$this->moderatorName);
        }

        if ($this->notes) {
            $mail->line('Notes: '.$this->notes);
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', config('app.name'));

        if ($fromAddress) {
            $mail->from($fromAddress, $fromName);
        }

        return $mail
            ->salutation("Regards,\n".$fromName)
            ->action('View your bookings', route('bookings.index'))
            ->line('Thank you for using '.$fromName.'.');
    }
}
