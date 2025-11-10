<?php

namespace App\Exports;

use App\Models\Booking;
use App\Support\BookingReportFilters;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookingReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = Booking::query()
            ->with([
                'user:id,name,email,phone',
                'room:id,name,building_id',
                'room.building:id,name,campus_id',
                'room.building.campus:id,name',
            ]);

        BookingReportFilters::apply($query, $this->filters);

        return $query
            ->orderByDesc('created_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal Pengajuan',
            'Status',
            'Nama Pemohon',
            'Email Pemohon',
            'No. Telepon',
            'Judul Kegiatan',
            'Deskripsi',
            'Mulai',
            'Selesai',
            'No. Surat',
            'Kampus',
            'Gedung',
            'Ruangan',
        ];
    }

    /**
     * @param  \App\Models\Booking  $booking
     */
    public function map($booking): array
    {
        return [
            $booking->id,
            $this->formatDateTime($booking->created_at),
            strtoupper((string) $booking->status),
            $booking->user?->name,
            $booking->user?->email,
            $booking->user?->phone,
            $booking->title,
            $booking->description,
            $this->formatDateTime($booking->start_time),
            $this->formatDateTime($booking->end_time),
            $booking->letter_number,
            $booking->room?->building?->campus?->name,
            $booking->room?->building?->name,
            $booking->room?->name,
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }
    }
}
