<?php

namespace App\Notifications;

use App\Models\ItemBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ItemBorrowingRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(protected ItemBorrowing $itemBorrowing)
    {
        // Relationships will be loaded by the controller before passing to notification
        // This prevents "undefined relationship" errors
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $borrowing = $this->itemBorrowing;
        $items = $borrowing->items instanceof Collection ? $borrowing->items : collect();

        $mail = (new MailMessage())
            ->subject('Pengajuan Peminjaman Barang Baru: ' . $borrowing->title)
            ->greeting('Halo Tim Sarpras,')
            ->line('Ada pengajuan peminjaman barang baru yang menunggu tindak lanjut Sarpras.')
            ->line('Pemohon: ' . $borrowing->user?->name . ' (' . $borrowing->user?->email . ')');

        // Tampilkan daftar barang (schema baru - multiple items)
        if ($items->isNotEmpty()) {
            $mail->line('Barang: ' . $items->map(function ($borrowingItem) {
                $item = $borrowingItem->item;

                if (! $item) {
                    return null;
                }

                return $item->name . ' (' . $item->code . ') x' . $borrowingItem->quantity;
            })->filter()->implode(', '));

            $mail->line('Total jenis barang: ' . $items->count());
        } elseif ($borrowing->singleItem?->name) {
            // Fallback ke schema lama (legacy)
            $mail->line('Barang: ' . $borrowing->singleItem->name . ' (' . $borrowing->singleItem->code . ')');
            $mail->line('Jumlah: ' . $borrowing->quantity);
        }

        // Periode peminjaman
        $startDate = $items->map->borrow_date->filter()->sort()->first() ?? $borrowing->borrow_date;
        $endDate = $items->map->return_date->filter()->sortDesc()->first() ?? $borrowing->return_date;

        if ($startDate && $endDate) {
            $mail->line('Periode: ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y'));
        }

        if ($borrowing->description) {
            $mail->line('Keperluan: ' . Str::limit($borrowing->description, 200));
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name', config('app.name'));

        if ($fromAddress) {
            $mail->from($fromAddress, $fromName);
        }

        return $mail
            ->salutation("Hormat kami,\n" . $fromName)
            ->action('Tinjau Pengajuan', route('admin.item-borrowings.show', $borrowing))
            ->line('Terima kasih telah mengelola permintaan inventaris barang dengan tertib.');
    }
}