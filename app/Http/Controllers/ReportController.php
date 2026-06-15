<?php

namespace App\Http\Controllers;

use App\Exports\BookingReportExport;
use App\Models\Booking;
use App\Support\BookingReportFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    private const STATUS_OPTIONS = ['waiting', 'needs_revision', 'approved', 'rejected', 'cancelled', 'expired'];

    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $filters = $this->validatedFilters($request);

        $query = Booking::query()
            ->with([
                'user:id,name,email,phone',
                'room:id,name,building_id',
                'room.building:id,name,campus_id',
                'room.building.campus:id,name',
                'roomSchedules.room.building.campus',
            ]);

        BookingReportFilters::apply($query, $filters);

        $bookings = $query
            ->orderByDesc('created_at')
            ->get()
            ->values();

        $statusSummary = [
            'total' => $bookings->count(),
            'approved' => $bookings->where('status', 'approved')->count(),
            'waiting' => $bookings->whereIn('status', Booking::WAITING_STATUSES)->count(),
            'rejected' => $bookings->where('status', 'rejected')->count(),
            'cancelled' => $bookings->where('status', 'cancelled')->count(),
            'expired' => $bookings->where('status', 'expired')->count(),
        ];

        return Inertia::render('Admin/Reports/Index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'statusSummary' => $statusSummary,
            'statusOptions' => self::STATUS_OPTIONS,
        ]);
    }

    public function export(Request $request)
    {
        $this->ensureAdmin($request);

        $filters = $this->validatedFilters($request);

        $fileName = 'booking-report-'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new BookingReportExport($filters), $fileName);
    }

    public function exportPdf(Request $request)
    {
        $this->ensureAdmin($request);

        $filters = $this->validatedFilters($request);

        $query = Booking::query()
            ->with([
                'user:id,name,email,phone',
                'room:id,name,building_id',
                'room.building:id,name,campus_id',
                'room.building.campus:id,name',
                'roomSchedules.room.building.campus',
            ]);

        BookingReportFilters::apply($query, $filters);

        $bookings = $query->orderByDesc('created_at')->get();

        $pdf = Pdf::loadView('pdf.booking-report', [
            'bookings' => $bookings,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        $fileName = 'booking-report-'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($fileName);
    }

    private function ensureAdmin(Request $request): void
    {
        if (! $request->user()?->canManageRoomModule()) {
            abort(403);
        }
    }

    /**
     * @return array<string, ?string>
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUS_OPTIONS)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'booking_start_date' => ['nullable', 'date'],
            'booking_end_date' => ['nullable', 'date'],
        ]);

        $filters = array_merge([
            'search' => null,
            'status' => null,
            'start_date' => null,
            'end_date' => null,
            'booking_start_date' => null,
            'booking_end_date' => null,
        ], $validated);

        foreach ($filters as $key => $value) {
            if ($value === '') {
                $filters[$key] = null;
            }
        }

        if ($filters['start_date'] && $filters['end_date'] && $filters['start_date'] > $filters['end_date']) {
            [$filters['start_date'], $filters['end_date']] = [$filters['end_date'], $filters['start_date']];
        }

        if (
            $filters['booking_start_date']
            && $filters['booking_end_date']
            && $filters['booking_start_date'] > $filters['booking_end_date']
        ) {
            [$filters['booking_start_date'], $filters['booking_end_date']] = [
                $filters['booking_end_date'],
                $filters['booking_start_date'],
            ];
        }

        return $filters;
    }
}
