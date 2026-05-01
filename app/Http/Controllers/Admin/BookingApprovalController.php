<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Exports\BookingExport;
use App\Models\LogHistory;
use App\Notifications\BookingStatusUpdatedNotification;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingApprovalController extends Controller
{
    /**
     * Display a listing of the bookings that require approval.
     */
    public function index()
    {
        $bookings = Booking::with(['user', 'room.building.campus'])
            ->orderByRaw("
                CASE
                    WHEN status IN ('waiting','pending') THEN 1
                    WHEN status = 'approved' THEN 2
                    WHEN status = 'cancelled' THEN 3
                    WHEN status = 'rejected' THEN 4
                    ELSE 5
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
    public function show(Booking $booking)
    {
        $booking->load([
            'user',
            'room.building.campus',
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
    public function updateStatus(Request $request, Booking $booking)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])],
                'notes'  => ['nullable', 'string', 'max:500'],
            ],
            [
                'notes.required' => 'Catatan wajib diisi ketika membatalkan booking.',
            ]
        );

        $validator->sometimes('notes', ['required', 'string', 'max:500'], function ($input) {
            return $input->status === 'cancelled';
        });

        $data = $validator->validate();

        if ($data['status'] === 'cancelled' && $booking->status !== 'approved') {
            return back()
                ->withErrors(['status' => 'Hanya booking yang sudah disetujui yang dapat dibatalkan.'])
                ->with('error', 'Hanya booking yang sudah disetujui yang dapat dibatalkan.');
        }

        $statusChanged = $booking->status !== $data['status'];

        if ($statusChanged) {
            $booking->update([
                'status' => $data['status'],
            ]);

            // Generate nomor surat jika booking disetujui
            if ($data['status'] === 'approved' && !$booking->letter_number) {
                $this->generateLetterNumber($booking);
            }
        }

        $description = match ($data['status']) {
            'approved' => 'Booking disetujui',
            'rejected' => 'Booking ditolak',
            'cancelled' => 'Booking dibatalkan oleh admin',
            default => 'Status booking diperbarui',
        };

        if (!empty($data['notes'])) {
            $description .= ' - ' . $data['notes'];
        }

        LogHistory::create([
            'booking_id'  => $booking->id,
            'user_id'     => Auth::id(),
            'action'      => $data['status'],
            'description' => $description,
        ]);

        if ($statusChanged) {
            $booking->load(['user', 'room.building.campus']);

            if ($booking->user && ! empty($booking->user->email)) {
                try {
                    $booking->user->notify(new BookingStatusUpdatedNotification(
                        $booking,
                        $data['status'],
                        $data['notes'] ?? null,
                        Auth::user()?->name
                    ));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        return redirect()->back()->with('success', 'Status booking berhasil diperbarui.');
    }

    public function exportExcel()
    {
        return Excel::download(new BookingExport(), 'data-booking-ruangan.xlsx');
    }

    public function exportPdf()
    {
        $bookings = Booking::with(['user', 'room.building.campus'])
            ->orderBy('start_time')
            ->get();

        $pdf = Pdf::loadView('pdf.booking-report', [
            'bookings' => $bookings,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('data-booking-ruangan.pdf');
    }

    /**
     * Generate nomor surat untuk booking yang disetujui.
     * Menggunakan transaksi database dengan lock untuk mencegah duplikasi nomor surat.
     */
    private function generateLetterNumber(Booking $booking): void
    {
        // Pengaman tambahan: cek jika nomor surat sudah ada
        if ($booking->letter_number) {
            return;
        }

        DB::transaction(function () use ($booking): void {
            // Refresh data booking untuk memastikan konsistensi
            $booking->refresh();

            if ($booking->letter_number) {
                return;
            }

            $issuedAt = now();
            $year = (int) $issuedAt->format('Y');
            $month = (int) $issuedAt->format('m');

            // Ambil urutan terakhir di bulan dan tahun yang sama dengan lock untuk mencegah race condition
            $latestSequence = Booking::whereYear('letter_generated_at', $year)
                ->whereMonth('letter_generated_at', $month)
                ->lockForUpdate()
                ->orderByDesc('letter_sequence')
                ->value('letter_sequence');

            $nextSequence = ((int) $latestSequence) + 1;

            // Format nomor surat: {sequence}/BAP-Bekasi/Booking/{bulan}/{tahun}
            $formattedNumber = sprintf(
                '%d/BAP-Bekasi/Booking/%s/%s',
                $nextSequence,
                $issuedAt->format('m'),
                $issuedAt->format('Y')
            );

            // Simpan informasi surat ke database
            $booking->letter_sequence = $nextSequence;
            $booking->letter_number = $formattedNumber;
            $booking->letter_generated_at = $issuedAt;
            $booking->save();
        });

        // Refresh booking setelah transaksi selesai
        $booking->refresh();
    }
}
