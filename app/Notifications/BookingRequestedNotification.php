<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Booking $booking)
    {
        $this->booking->loadMissing(['user', 'room.building.campus']);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $room = $booking->room;
        $building = $room?->building;
        $campus = $building?->campus;

        $start = $booking->start_time ? Carbon::parse($booking->start_time) : null;
        $end = $booking->end_time ? Carbon::parse($booking->end_time) : null;

        $mail = (new MailMessage())
            ->subject('New Booking Request: '.$booking->title)
            ->greeting('Hello Admin,')
            ->line('A new room booking request was submitted and needs your review.')
            ->line('Requester: '.$booking->user?->name.' ('.$booking->user?->email.')');

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

        if ($booking->description) {
            $mail->line('Purpose: '.Str::limit($booking->description, 200));
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', config('app.name'));

        if ($fromAddress) {
            $mail->from($fromAddress, $fromName);
        }

        return $mail
            ->salutation("Regards,\n".$fromName)
            ->action('Review Booking', route('admin.bookings.show', $booking))
            ->line('Thank you for keeping our campus schedule running smoothly.');
    }
}
