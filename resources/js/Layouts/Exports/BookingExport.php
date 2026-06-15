<?php

namespace App\Exports;

use App\Models\Booking;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookingExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
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
            'Judul',
            'Pemohon',
            'Email Pemohon',
            'Ruangan',
            'Gedung',
            'Kampus',
            'Mulai',
            'Selesai',
            'Status',
        ];
    }

    /**
     * @param  \App\Models\Booking  $booking
     */
    public function map($booking): array
    {
        $statusLabels = [
            'waiting' => 'Menunggu Persetujuan',
            'needs_revision' => 'Perlu Direvisi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
        ];

        $status = $this->normalizeStatus($booking->status);

        return [
            $booking->id,
            $booking->title,
            optional($booking->user)->name,
            optional($booking->user)->email,
            optional($booking->room)->name,
            optional(optional($booking->room)->building)->name,
            optional(optional(optional($booking->room)->building)->campus)->name,
            $this->formatDateTime($booking->start_time),
            $this->formatDateTime($booking->end_time),
            $statusLabels[$status] ?? ucfirst($status ?? '-'),
        ];
    }

    protected function formatDateTime(?string $value): string
    {
        if (! $value) {
            return '-';
        }

        return Carbon::parse($value)->timezone('Asia/Jakarta')->format('d-m-Y H:i');
    }

    protected function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return in_array($status, ['pending', 'requested'], true) ? 'waiting' : $status;
    }
}
