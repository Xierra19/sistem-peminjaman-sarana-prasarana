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
        $statusLabel = match ($this->status) {
            ItemBorrowing::STATUS_APPROVED => 'Disetujui',
            ItemBorrowing::STATUS_NEEDS_REVISION => 'Perlu Direvisi',
            ItemBorrowing::STATUS_REJECTED => 'Ditolak',
            ItemBorrowing::STATUS_CANCELLED => 'Dibatalkan',
            default => Str::headline($this->status),
        };
        $items = $borrowing->items instanceof Collection ? $borrowing->items : collect();

        $mail = (new MailMessage)
            ->subject($statusLabel.' Peminjaman Barang: '.$borrowing->title)
            ->greeting('Halo '.$borrowing->user?->name.',');

        if ($this->status === ItemBorrowing::STATUS_NEEDS_REVISION) {
            $mail->line('Tim Sarpras meminta perbaikan pada pengajuan Anda. Silakan buka detail pengajuan dan kirim revisi.');
        } elseif ($this->status === ItemBorrowing::STATUS_CANCELLED) {
            $mail->line('Peminjaman barang Anda telah dibatalkan oleh Tim Sarpras.');
        } else {
            $mail->line('Status pengajuan peminjaman barang Anda: '.Str::lower($statusLabel).'.');
        }

        if ($items->isNotEmpty()) {
            $itemGroups = $items->groupBy('item_id');
            $mail->line('Barang: '.$itemGroups->map(function ($schedules) {
                $borrowingItem = $schedules->first();
                $item = $borrowingItem?->item;

                if (! $item) {
                    return null;
                }

                $quantities = $schedules
                    ->pluck('quantity')
                    ->map(fn ($quantity): int => (int) $quantity)
                    ->unique()
                    ->sort()
                    ->implode('/');

                return $item->name.' x'.$quantities.' per jadwal';
            })->filter()->implode(', '));
        } elseif ($borrowing->singleItem?->name) {
            $mail->line('Barang: '.$borrowing->singleItem->name);
        }

        if ($items->isNotEmpty()) {
            $mail->line('Total jenis barang: '.$items->pluck('item_id')->unique()->count());
        } elseif ($borrowing->quantity) {
            $mail->line('Jumlah: '.$borrowing->quantity);
        }

        $startDate = $items->map->borrow_date->filter()->sort()->first() ?? $borrowing->borrow_date;
        $endDate = $items->map->return_date->filter()->sortDesc()->first() ?? $borrowing->return_date;

        if ($startDate && $endDate) {
            $mail->line('Periode: '.$startDate->timezone(config('app.business_timezone'))->format('d M Y H:i').' - '.$endDate->timezone(config('app.business_timezone'))->format('d M Y H:i').' WIB');
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
            ->action('Lihat Detail Pengajuan', route('item-borrowings.show', $borrowing))
            ->line('Terima kasih telah menggunakan '.$fromName.'.');
    }
}
