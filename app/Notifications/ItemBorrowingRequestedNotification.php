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
            ->subject('Pengajuan Peminjaman Barang Baru: '.$borrowing->title)
            ->greeting('Halo Tim Sarpras,')
            ->line('Ada pengajuan peminjaman barang baru yang menunggu tindak lanjut Sarpras.')
            ->line('Pemohon: '.$borrowing->user?->name.' ('.$borrowing->user?->email.')');

        if ($item?->name) {
            $mail->line('Barang: '.$item->name.' ('.$item->code.')');
        }

        $mail
            ->line('Jumlah: '.$borrowing->quantity)
            ->line('Periode: '.$borrowing->borrow_date?->format('d M Y').' - '.$borrowing->return_date?->format('d M Y'));

        if ($borrowing->description) {
            $mail->line('Keperluan: '.Str::limit($borrowing->description, 200));
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', config('app.name'));

        if ($fromAddress) {
            $mail->from($fromAddress, $fromName);
        }

        return $mail
            ->salutation("Hormat kami,\n".$fromName)
            ->action('Tinjau Pengajuan', route('admin.item-borrowings.show', $borrowing))
            ->line('Terima kasih telah mengelola permintaan inventaris barang dengan tertib.');
    }
}
