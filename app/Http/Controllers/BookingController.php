<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Campus;
use App\Models\LogHistory;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Inertia\Inertia;

class BookingController extends Controller
{
    /**
     * Display a listing of the authenticated user's booking requests.
     */
    public function index()
    {
        $bookings = Booking::with([
            'room.building.campus',
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ])
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $campuses = Campus::query()
            ->with(['buildings' => function ($query) {
                $query->select('id', 'name', 'campus_id')
                    ->orderBy('name')
                    ->with(['rooms' => function ($roomQuery) {
                        $roomQuery->select('id', 'name', 'building_id', 'capacity', 'is_available')
                            ->orderBy('name');
                    }]);
            }])
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Bookings/Create', [
            'campuses' => $campuses,
        ]);
    }

    /**
     * Store a newly created booking in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id'     => 'required|exists:rooms,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after:start_time',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,png|max:2048',
        ]);

        // Upload file kalau ada
        if ($request->hasFile(key: 'attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('attachments', 'public');
        }

        // Tambah user id yang login & status default
        $validated['user_id'] = Auth::id();
        $validated['status']  = 'pending';

        // Simpan booking
        $room = Room::with('bookings')->findOrFail($validated['room_id']);

        if (! $room->is_available) {
            return back()
                ->withErrors([
                    'start_time' => 'Ruangan sudah dibooking pada rentang waktu yang dipilih.',
                ])
                ->with('error', 'Ruangan sudah dibooking pada rentang waktu yang dipilih.')
                ->withInput();
        }

        $start = Carbon::parse($validated['start_time']);
        $end   = Carbon::parse($validated['end_time']);

        $hasConflict = $room->bookings()
            ->where('status', '!=', 'rejected')
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();

        if ($hasConflict) {
            return back()
                ->withErrors([
                    'room_id' => 'Ruangan sedang tidak tersedia untuk dibooking.',
                ])
                ->with('error', 'Ruangan sedang tidak tersedia untuk dibooking.')
                ->withInput();
        }

        $booking = Booking::create($validated);


        LogHistory::create([
            'booking_id'  => $booking->id,
            'user_id'     => Auth::id(),
            'action'      => 'requested',
            'description' => 'Booking diajukan oleh pengguna.',
        ]);

        return redirect()->route('bookings.index')->with('success', 'Booking berhasil dibuat dan menunggu persetujuan.');
    }

    /**
     * Return availability information for a room on a specific date.
     */
    public function availability(Request $request, Room $room)
    {
        if (! $room->is_available) {
            return response()->json([
                'available' => false,
                'bookings' => [],
                'message' => 'Ruangan saat ini ditandai tidak tersedia.',
            ]);
        }

        $date = $request->query('date');

        if (! $date) {
            return response()->json([
                'available' => true,
                'bookings' => [],
            ]);
        }

        try {
            $day = Carbon::parse($date);
        } catch (\Throwable $exception) {
            return response()->json([
                'available' => true,
                'bookings' => [],
            ], 422);
        }
        $startOfDay = $day->copy()->startOfDay();
        $endOfDay = $day->copy()->endOfDay();

        $bookings = $room->bookings()
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startOfDay, $endOfDay) {
                $query->whereBetween('start_time', [$startOfDay, $endOfDay])
                    ->orWhereBetween('end_time', [$startOfDay, $endOfDay])
                    ->orWhere(function ($subQuery) use ($startOfDay, $endOfDay) {
                        $subQuery->where('start_time', '<', $startOfDay)
                            ->where('end_time', '>', $endOfDay);
                    });
            })
            ->get()
            ->map(function (Booking $booking) {
                return [
                    'id' => $booking->id,
                    'title' => $booking->title,
                    'status' => $booking->status,
                    'start' => Carbon::parse($booking->start_time)->format('H:i'),
                    'end' => Carbon::parse($booking->end_time)->format('H:i'),
                ];
            });

        return response()->json([
            'available' => true,
            'bookings' => $bookings,
        ]);
    }
}