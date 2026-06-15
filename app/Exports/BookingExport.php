<?php

namespace App\Exports;

use App\Models\Booking;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookingExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function collection()
    {
        return Booking::with(['user', 'roomSchedules.room.building.campus'])
            ->orderBy('start_time')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Pemohon',
            'Email',
            'Ruangan dan Jadwal',
            'Judul',
            'Deskripsi',
            'Status',
        ];
    }

    public function map($booking): array
    {
        $statusLabels = [
            'waiting' => 'Menunggu Persetujuan',
            'pending' => 'Menunggu Persetujuan',
            'requested' => 'Menunggu Persetujuan',
            'needs_revision' => 'Perlu Direvisi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
        ];

        return [
            $booking->id,
            optional($booking->user)->name,
            optional($booking->user)->email,
            $booking->roomSchedules->map(function ($schedule): string {
                $location = collect([
                    $schedule->room?->building?->campus?->name,
                    $schedule->room?->building?->name,
                    $schedule->room?->name,
                ])->filter()->join(' / ');

                return $location.' - '.$schedule->schedule_summary;
            })->join('; '),
            $booking->title,
            $booking->description,
            $statusLabels[$booking->status] ?? $booking->status,
        ];
    }
}
