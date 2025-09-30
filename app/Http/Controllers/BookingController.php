<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;


class BookingController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Ambil semua data ruangan dari database
        $rooms = Room::all();

        // Kembalikan view Vue.js dan kirimkan data ruangan
        return Inertia::render('Bookings/Create', [
            'rooms' => $rooms,
        ]);
    }

    /**
     * Store a newly created booking in storage.
     */
    public function store(Request $request)
    {
        // Validasi data
        $validated = $request->validate([
            'room_id'     => 'required|exists:rooms,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after:start_time',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,png|max:2048',
        ]);

        // Upload file kalau ada
        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('attachments', 'public');
        }

        // Tambah user id yang login & status default
        $validated['user_id'] = Auth::id();
        $validated['status']  = 'pending';

        // Simpan booking
        Booking::create($validated);

        // Redirect ke dashboard
        return redirect()->route('dashboard')->with('success', 'Booking berhasil dibuat!');
    }
}
