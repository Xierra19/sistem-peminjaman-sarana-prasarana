<?php

namespace App\Notifications;

use App\Models\ItemBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ItemBorrowingStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected ItemBorrowing $itemBorrowing,
        protected string $status,
        protected ?string $notes = null,
        protected ?string $moderatorName = null,
    ) {
        $this->itemBorrowing->loadMissing(['item', 'user']);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $borrowing = $this->itemBorrowing;
        $item = $borrowing->item;
        $status = Str::headline($this->status);

        $mail = (new MailMessage())
            ->subject($status.' Item Borrowing: '.$borrowing->title)
            ->greeting('Hello '.$borrowing->user?->name.',');

        $mail->line('Your item borrowing request has been '.$status.'.');

        if ($item?->name) {
            $mail->line('Item: '.$item->name.' ('.$item->code.')');
        }

        $mail
            ->line('Quantity: '.$borrowing->quantity)
            ->line('Period: '.$borrowing->borrow_date?->format('d M Y').' - '.$borrowing->return_date?->format('d M Y'));

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
            ->action('View your requests', route('item-borrowings.index'))
            ->line('Thank you for using '.$fromName.'.');
    }
}
