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
        $query = LogHistory::with(['booking.room.building.campus', 'user'])
            ->orderBy('created_at', 'desc');

        if ($this->user->role !== 'admin') {
            $query->whereHas('booking', function ($q) {
                $q->where('user_id', $this->user->id);
            });
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
            'Kampus',
            'Gedung',
            'Ruang',
            'Booking ID',
        ];
    }

    public function map($log): array
    {
        $room = optional($log->booking)->room;
        $building = optional($room)->building;
        $campus = optional($building)->campus;

        return [
            optional($log->created_at)->format('Y-m-d H:i:s'),
            optional($log->user)->name,
            $log->action,
            $log->description,
            optional($campus)->name,
            optional($building)->name,
            optional($room)->name,
            optional($log->booking)->id,
        ];
    }
}

