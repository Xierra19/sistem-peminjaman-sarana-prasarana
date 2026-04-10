<?php

namespace App\Notifications;

use App\Models\ItemBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ItemBorrowingRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(protected ItemBorrowing $itemBorrowing)
    {
        $this->itemBorrowing->loadMissing(['user', 'item']);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $borrowing = $this->itemBorrowing;
        $item = $borrowing->item;

        $mail = (new MailMessage())
            ->subject('New Item Borrowing Request: '.$borrowing->title)
            ->greeting('Hello Admin,')
            ->line('A new item borrowing request was submitted and needs your review.')
            ->line('Requester: '.$borrowing->user?->name.' ('.$borrowing->user?->email.')');

        if ($item?->name) {
            $mail->line('Item: '.$item->name.' ('.$item->code.')');
        }

        $mail
            ->line('Quantity: '.$borrowing->quantity)
            ->line('Period: '.$borrowing->borrow_date?->format('d M Y').' - '.$borrowing->return_date?->format('d M Y'));

        if ($borrowing->description) {
            $mail->line('Purpose: '.Str::limit($borrowing->description, 200));
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', config('app.name'));

        if ($fromAddress) {
            $mail->from($fromAddress, $fromName);
        }

        return $mail
            ->salutation("Regards,\n".$fromName)
            ->action('Review Request', route('admin.item-borrowings.show', $borrowing))
            ->line('Thank you for reviewing the borrowing queue.');
    }
}
