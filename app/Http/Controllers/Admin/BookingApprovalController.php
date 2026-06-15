<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BookingExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Services\BookingApprovalWorkflow;
use App\Services\ExpirePendingBookings;
use App\Support\AdminStatusTransitionResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BookingApprovalController extends Controller
{
    /**
     * Display a listing of the bookings that require approval.
     */
    public function index(ExpirePendingBookings $expirePendingBookings)
    {
        $expirePendingBookings->handle();

        $bookings = Booking::with(['user', 'room.building.campus', 'roomSchedules.room.building.campus'])
            ->orderByRaw("
                CASE
                    WHEN status IN ('waiting','pending') THEN 1
                    WHEN status = 'approved' THEN 2
                    WHEN status = 'expired' THEN 3
                    WHEN status = 'cancelled' THEN 4
                    WHEN status = 'rejected' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy('start_time')
            ->get();

        return Inertia::render('Admin/Bookings/Index', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * Display the specified booking with its audit trail.
     */
    public function show(Booking $booking, ExpirePendingBookings $expirePendingBookings)
    {
        $expirePendingBookings->expireIfPastDue($booking);

        $booking->load([
            'user',
            'room.building.campus',
            'roomSchedules.room.building.campus',
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ]);

        return Inertia::render('Admin/Bookings/Show', [
            'booking' => $booking,
        ]);
    }

    /**
     * Update the status of a booking (approve / reject).
     */
    public function updateStatus(
        UpdateBookingStatusRequest $request,
        Booking $booking,
        BookingApprovalWorkflow $workflow,
    ) {
        $data = $request->validated();
        $result = $workflow->update(
            $booking,
            $data['status'],
            $data['notes'] ?? null,
            $request->user(),
        );

        if ($result === AdminStatusTransitionResult::Expired) {
            return back()
                ->withErrors(['status' => 'Permintaan sudah kedaluwarsa karena hari peminjaman terakhir telah berakhir.'])
                ->with('error', 'Permintaan sudah kedaluwarsa dan tidak dapat diproses.');
        }

        if ($result === AdminStatusTransitionResult::PendingRequired) {
            return back()
                ->withErrors(['status' => 'Hanya booking yang masih menunggu yang dapat disetujui atau ditolak.'])
                ->with('error', 'Status booking sudah final dan tidak dapat diproses kembali.');
        }

        if ($result === AdminStatusTransitionResult::ApprovedRequired) {
            return back()
                ->withErrors(['status' => 'Hanya booking yang sudah disetujui yang dapat dibatalkan.'])
                ->with('error', 'Hanya booking yang sudah disetujui yang dapat dibatalkan.');
        }

        return redirect()->back()->with('success', 'Status booking berhasil diperbarui.');
    }

    public function exportExcel()
    {
        return Excel::download(new BookingExport, 'data-booking-ruangan.xlsx');
    }

    public function exportPdf()
    {
        $bookings = Booking::with(['user', 'room.building.campus', 'roomSchedules.room.building.campus'])
            ->orderBy('start_time')
            ->get();

        $pdf = Pdf::loadView('pdf.booking-report', [
            'bookings' => $bookings,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('data-booking-ruangan.pdf');
    }
}
