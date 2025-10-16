<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LogHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BookingApprovalController extends Controller
{
    /**
     * Display a listing of the bookings that require approval.
     */
    public function index()
    {
        $bookings = Booking::with(['user', 'room.building.campus'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END")
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
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes'  => 'nullable|string|max:500',
        ]);

        $statusChanged = $booking->status !== $data['status'];

        if ($statusChanged) {
            $booking->update([
                'status' => $data['status'],
            ]);
        }

        $description = $data['status'] === 'approved'
            ? 'Booking disetujui'
            : 'Booking ditolak';

        if (!empty($data['notes'])) {
            $description .= ' - ' . $data['notes'];
        }

        LogHistory::create([
            'booking_id'  => $booking->id,
            'user_id'     => Auth::id(),
            'action'      => $data['status'],
            'description' => $description,
        ]);

        return redirect()->back()->with('success', 'Status booking berhasil diperbarui.');
    }
}