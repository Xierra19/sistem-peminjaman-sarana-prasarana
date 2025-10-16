<?php

namespace App\Exports;

use App\Models\LogHistory;
use App\Models\User; // ← Tambahkan ini
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HistoryExport implements FromCollection, WithHeadings, WithMapping
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $query = LogHistory::with(['booking.room.building.campus', 'user'])
            ->orderByDesc('created_at');

        if ($this->user->role !== 'admin') {
            $query->whereHas('booking', function ($builder) {
                $builder->where('user_id', $this->user->id);
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Pemohon',
            'Email Pemohon',
            'Ruangan',
            'Gedung',
            'Kampus',
            'Aksi',
            'Waktu',
            'Deskripsi',
        ];
    }

    public function map($history): array
    {
        return [
            $history->id,
            optional($history->user)->name ?? '-',
            optional($history->user)->email ?? '-',
            optional(optional($history->booking)->room)->name ?? '-',
            optional(optional(optional($history->booking)->room)->building)->name ?? '-',
            optional(optional(optional(optional($history->booking)->room)->building)->campus)->name ?? '-',
            ucfirst($history->action ?? '-'),
            $this->formatDateTime($history->created_at),
            $history->description ?? '-',
        ];
    }

    protected function formatDateTime($value): string
    {
        if (empty($value)) {
            return '-';
        }

        return Carbon::parse($value)
            ->timezone('Asia/Jakarta')
            ->format('d-m-Y H:i');
    }
}
