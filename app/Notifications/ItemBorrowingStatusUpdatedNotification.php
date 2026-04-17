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
            ->subject($status.' Peminjaman Barang: '.$borrowing->title)
            ->greeting('Halo '.$borrowing->user?->name.',');

        $mail->line('Pengajuan peminjaman barang Anda telah '.$status.' oleh tim Sarpras.');

        if ($item?->name) {
            $mail->line('Barang: '.$item->name.' ('.$item->code.')');
        }

        $mail
            ->line('Jumlah: '.$borrowing->quantity)
            ->line('Periode: '.$borrowing->borrow_date?->format('d M Y').' - '.$borrowing->return_date?->format('d M Y'));

        if ($this->moderatorName) {
            $mail->line('Diproses oleh: '.$this->moderatorName);
        }

        if ($this->notes) {
            $mail->line('Catatan: '.$this->notes);
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', config('app.name'));

        if ($fromAddress) {
            $mail->from($fromAddress, $fromName);
        }

        return $mail
            ->salutation("Hormat kami,\n".$fromName)
            ->action('Lihat Pengajuan', route('item-borrowings.index'))
            ->line('Terima kasih telah menggunakan '.$fromName.'.');
    }
}
