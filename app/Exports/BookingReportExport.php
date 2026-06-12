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
                'roomSchedules.room.building.campus',
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
            'Ruangan dan Jadwal',
            'No. Surat',
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
            $booking->roomSchedules->map(function ($schedule): string {
                $location = collect([
                    $schedule->room?->building?->campus?->name,
                    $schedule->room?->building?->name,
                    $schedule->room?->name,
                ])->filter()->join(' / ');

                return $location.' - '.$schedule->schedule_summary;
            })->join('; '),
            $booking->letter_number,
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
