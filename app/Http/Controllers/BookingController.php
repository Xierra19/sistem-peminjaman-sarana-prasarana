<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Campus;
use App\Models\LogHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;

use Inertia\Inertia;
use App\Notifications\BookingRequestedNotification;

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
     * Display a specific booking requested by the authenticated user.
     */
    public function show(Booking $booking)
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $booking->user_id !== $user->id) {
            abort(403);
        }

        $booking->load([
            'room.building.campus',
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ]);

        $latestDecisionLog = $booking->logs
            ->filter(fn (LogHistory $log) => in_array($log->action, ['approved', 'rejected', 'cancelled']))
            ->last();

        return Inertia::render('Bookings/Show', [
            'booking' => $booking,
            'latestDecisionLog' => $latestDecisionLog,
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
        $minimumStartTime = now()->addDays(3)->startOfDay();
        $minStartDateLabel = $minimumStartTime->format('d/m/Y');

        $validated = $request->validate([
            'room_id'     => 'required|exists:rooms,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time'  => 'required|date|after_or_equal:'.$minimumStartTime->toDateTimeString(),
            'end_time'    => 'required|date|after:start_time',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,png|max:2048',
        ], [
            'start_time.after_or_equal' => 'Tanggal penggunaan minimal '.$minStartDateLabel.' (H+3 dari hari ini).',
        ]);

        // Upload file kalau ada
        if ($request->hasFile(key: 'attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('attachments', 'public');
        }

        // Tambah user id yang login & status default
        $validated['user_id'] = Auth::id();
        $validated['status']  = 'waiting';

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
            ->whereNotIn('status', ['rejected', 'cancelled'])
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

        $booking->load(['user', 'room.building.campus']);

        $admins = User::query()
            ->whereIn('role', User::roomAdminRoles())
            ->whereNotNull('email')
            ->get();

        if ($admins->isNotEmpty()) {
            try {
                Notification::send($admins, new BookingRequestedNotification($booking));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

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
            ], 200);
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
            ->whereNotIn('status', ['rejected', 'cancelled'])
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
        ], 200);
    }

    public function downloadAttachment(Booking $booking)
    {
        $user = Auth::user();

        if (! $booking->attachment) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (! $user->isAdmin() && $booking->user_id !== $user->id) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($booking->attachment)) {
            abort(404, 'File lampiran tidak tersedia.');
        }

        return response()->download(Storage::disk('public')->path($booking->attachment), basename($booking->attachment));
    }

    public function cancel(Booking $booking)
    {
        $user = Auth::user();

        if ($booking->user_id !== $user->id) {
            abort(403);
        }

        $cancellableStatuses = ['waiting', 'pending', 'requested'];

        if (! in_array($booking->status, $cancellableStatuses, true)) {
            $message = $booking->status === 'cancelled'
                ? 'Booking sudah dibatalkan sebelumnya.'
                : 'Booking tidak dapat dibatalkan karena sudah diproses oleh admin.';

            return redirect()->back()->with('error', $message);
        }

        DB::transaction(function () use ($booking, $user): void {
            $booking->update([
                'status' => 'cancelled',
            ]);

            LogHistory::create([
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'action' => 'cancelled',
                'description' => 'Booking dibatalkan oleh pemohon.',
            ]);
        });

        return redirect()->back()->with('success', 'Booking berhasil dibatalkan.');
    }

    public function downloadLetter(Booking $booking)
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $booking->user_id !== $user->id) {
            abort(403);
        }

        if ($booking->status !== 'approved') {
            abort(403, 'Booking belum disetujui.');
        }

        $booking->load(['user', 'room.building.campus']);
        $approvedAt = $booking->logs()
            ->where('action', 'approved')
            ->latest('created_at')
            ->value('created_at');
        $approvedAt = $approvedAt ? Carbon::parse($approvedAt) : null;
        $this->ensureLetterNumber($booking);

        $pdf = Pdf::loadView('pdf.booking-letter', [
            'booking' => $booking,
            'generatedAt' => now(),
            'approvedAt' => $approvedAt,
        ])->setPaper('a4');

        $filename = 'Surat-Peminjaman-Ruangan-' . $booking->id . '.pdf';

        return $pdf->download($filename);
    }

    private function ensureLetterNumber(Booking $booking): void
    {
        if ($booking->letter_number) {
            return;
        }

        DB::transaction(function () use ($booking): void {
            $booking->refresh();

            if ($booking->letter_number) {
                return;
            }

            $issuedAt = now();
            $year = (int) $issuedAt->format('Y');
            $month = (int) $issuedAt->format('m');

            $latestSequence = Booking::whereYear('letter_generated_at', $year)
                ->whereMonth('letter_generated_at', $month)
                ->lockForUpdate()
                ->orderByDesc('letter_sequence')
                ->value('letter_sequence');

            $nextSequence = ((int) $latestSequence) + 1;

            $formattedNumber = sprintf(
                '%d/BAP-Bekasi/Booking/%s/%s',
                $nextSequence,
                $issuedAt->format('m'),
                $issuedAt->format('Y')
            );

            $booking->letter_sequence = $nextSequence;
            $booking->letter_number = $formattedNumber;
            $booking->letter_generated_at = $issuedAt;
            $booking->save();
        });

        $booking->refresh();
    }
}
