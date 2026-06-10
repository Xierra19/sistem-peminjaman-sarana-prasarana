<?php

namespace App\Exports;

use App\Models\Booking;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BookingExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return Booking::with(['user', 'room.building.campus'])
            ->orderBy('start_time')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Pemohon',
            'Email',
            'Kampus',
            'Gedung',
            'Ruang',
            'Judul',
            'Deskripsi',
            'Mode Jadwal',
            'Jadwal',
            'Status',
        ];
    }

    public function map($booking): array
    {
        $statusLabels = [
            'waiting' => 'Menunggu Persetujuan',
            'pending' => 'Menunggu Persetujuan',
            'requested' => 'Menunggu Persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
        ];

        return [
            $booking->id,
            optional($booking->user)->name,
            optional($booking->user)->email,
            optional(optional(optional($booking->room)->building)->campus)->name,
            optional(optional($booking->room)->building)->name,
            optional($booking->room)->name,
            $booking->title,
            $booking->description,
            $booking->schedule_mode_label,
            $booking->schedule_summary,
            $statusLabels[$booking->status] ?? $booking->status,
        ];
    }
}
