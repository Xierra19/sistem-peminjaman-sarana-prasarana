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
            'Mulai',
            'Selesai',
            'Status',
        ];
    }

    public function map($booking): array
    {
        return [
            $booking->id,
            optional($booking->user)->name,
            optional($booking->user)->email,
            optional(optional(optional($booking->room)->building)->campus)->name,
            optional(optional($booking->room)->building)->name,
            optional($booking->room)->name,
            $booking->title,
            $booking->description,
            optional($booking->start_time)->format('Y-m-d H:i'),
            optional($booking->end_time)->format('Y-m-d H:i'),
            $booking->status,
        ];
    }
}
