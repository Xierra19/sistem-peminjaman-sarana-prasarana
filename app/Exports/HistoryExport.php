<?php

namespace App\Exports;

use App\Models\LogHistory;
use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class HistoryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private User $user)
    {
    }

    public function collection()
    {
        $query = LogHistory::with(['booking.roomSchedules.room.building.campus', 'user'])
            ->orderByDesc('created_at');

        if (! $this->user->canManageHistory()) {
            $query->where('user_id', $this->user->id);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Waktu',
            'User',
            'Aksi',
            'Deskripsi',
            'Ruangan',
            'Booking ID',
        ];
    }

    public function map($log): array
    {
        return [
            optional($log->created_at)->format('Y-m-d H:i:s'),
            optional($log->user)->name,
            $log->action,
            $log->description,
            optional($log->booking)->room_summary,
            optional($log->booking)->id,
        ];
    }
}
