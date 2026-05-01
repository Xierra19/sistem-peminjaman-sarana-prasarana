<?php

namespace App\Notifications;

use App\Models\ItemBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ItemBorrowingStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ItemBorrowing $itemBorrowing,
        protected string $status,
        protected ?string $notes = null,
        protected ?string $moderatorName = null,
    ) {
        $this->itemBorrowing->loadMissing(['items.item', 'singleItem', 'user']);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $borrowing = $this->itemBorrowing;
        $status = Str::headline($this->status);
        $items = $borrowing->items instanceof Collection ? $borrowing->items : collect();

        $mail = (new MailMessage())
            ->subject($status.' Peminjaman Barang: '.$borrowing->title)
            ->greeting('Halo '.$borrowing->user?->name.',');

        $mail->line('Pengajuan peminjaman barang Anda telah '.$status.' oleh tim Sarpras.');

        if ($items->isNotEmpty()) {
            $mail->line('Barang: '.$items->map(function ($borrowingItem) {
                $item = $borrowingItem->item;

                if (! $item) {
                    return null;
                }

                return $item->name.' ('.$item->code.') x'.$borrowingItem->quantity;
            })->filter()->implode(', '));
        } elseif ($borrowing->singleItem?->name) {
            $mail->line('Barang: '.$borrowing->singleItem->name.' ('.$borrowing->singleItem->code.')');
        }

        if ($items->isNotEmpty()) {
            $mail->line('Total jenis barang: '.$items->count());
        } elseif ($borrowing->quantity) {
            $mail->line('Jumlah: '.$borrowing->quantity);
        }

        $startDate = $items->map->borrow_date->filter()->sort()->first() ?? $borrowing->borrow_date;
        $endDate = $items->map->return_date->filter()->sortDesc()->first() ?? $borrowing->return_date;

        if ($startDate && $endDate) {
            $mail->line('Periode: '.$startDate->format('d M Y').' - '.$endDate->format('d M Y'));
        }

        if ($this->moderatorName) {
            $mail->line('Diproses oleh: '.$this->moderatorName);
        }

        if ($this->notes) {
            $mail->line('Catatan: '.$this->notes);
        }

        if ($this->status === 'approved' && $borrowing->signed_letter) {
            $mail->line('Surat persetujuan yang telah ditandatangani sudah tersedia pada detail peminjaman barang Anda.');
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
