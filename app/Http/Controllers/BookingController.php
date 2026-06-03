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
    public function index(Request $request)
    {
        // Ambil parameter dari request
        $search = $request->input('search');
        $status = $request->input('status');
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $perPage = $request->input('per_page', 10);

        // Flatten response dengan join, hindari nested relationships
        $query = Booking::query()
            ->join('rooms', 'bookings.room_id', '=', 'rooms.id')
            ->join('buildings', 'rooms.building_id', '=', 'buildings.id')
            ->join('campuses', 'buildings.campus_id', '=', 'campuses.id')
            ->select(
                'bookings.id',
                'bookings.title',
                'bookings.description',
                'bookings.start_time',
                'bookings.end_time',
                'bookings.schedule_mode',
                'bookings.schedule_start_date',
                'bookings.schedule_end_date',
                'bookings.schedule_start_clock',
                'bookings.schedule_end_clock',
                'bookings.status',
                'rooms.name as room_name',
                'buildings.name as building_name',
                'campuses.name as campus_name'
            )
            ->where('bookings.user_id', Auth::id());

        // Filter pencarian (server-side)
        $query->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('bookings.title', 'like', "%{$search}%")
                    ->orWhere('bookings.description', 'like', "%{$search}%")
                    ->orWhere('rooms.name', 'like', "%{$search}%")
                    ->orWhere('buildings.name', 'like', "%{$search}%")
                    ->orWhere('campuses.name', 'like', "%{$search}%");
            });
        });

        // Filter status
        $query->when($status, function ($q) use ($status) {
            $q->where('bookings.status', $status);
        });

        // Sorting (flattened field mapping)
        $sortColumn = match ($sort) {
            'room' => 'rooms.name',
            default => "bookings.{$sort}",
        };
        $query->orderBy($sortColumn, $direction);

        // Pagination
        $bookings = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
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
        $minimumStartDate = now()->addDays(3)->startOfDay();
        $minStartDateLabel = $minimumStartDate->format('d/m/Y');

        $validated = $request->validate([
            'room_id'     => 'required|exists:rooms,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'schedule_mode' => 'required|in:'.Booking::MODE_CONTINUOUS.','.Booking::MODE_SAME_HOURS_DAILY,
            'start_date'  => 'required|date|after_or_equal:'.$minimumStartDate->toDateString(),
            'end_date'    => 'required|date|after_or_equal:start_date',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,png|max:2048',
        ], [
            'start_date.after_or_equal' => 'Tanggal penggunaan minimal '.$minStartDateLabel.' (H+3 dari hari ini).',
        ]);

        $schedulePayload = $this->buildSchedulePayload($validated);
        $startDateTime = Carbon::parse($schedulePayload['start_time']);
        $endDateTime = Carbon::parse($schedulePayload['end_time']);

        if ($validated['schedule_mode'] === Booking::MODE_SAME_HOURS_DAILY && $validated['end_time'] <= $validated['start_time']) {
            return back()
                ->withErrors([
                    'end_time' => 'Jam selesai harus setelah jam mulai untuk mode jam sama pada rentang tanggal.',
                ])
                ->withInput();
        }

        if ($validated['schedule_mode'] === Booking::MODE_CONTINUOUS && $endDateTime->lte($startDateTime)) {
            return back()
                ->withErrors([
                    'end_time' => 'Waktu selesai harus setelah waktu mulai untuk mode kontinu antar hari.',
                ])
                ->withInput();
        }

        // Upload file kalau ada
        if ($request->hasFile(key: 'attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('attachments', 'public');
        }

        // Tambah user id yang login & status default
        $validated['user_id'] = Auth::id();
        $validated['status']  = 'waiting';
        $validated['start_time'] = $schedulePayload['start_time'];
        $validated['end_time'] = $schedulePayload['end_time'];
        $validated['schedule_start_date'] = $schedulePayload['schedule_start_date'];
        $validated['schedule_end_date'] = $schedulePayload['schedule_end_date'];
        $validated['schedule_start_clock'] = $schedulePayload['schedule_start_clock'];
        $validated['schedule_end_clock'] = $schedulePayload['schedule_end_clock'];

        // Simpan booking
        $room = Room::findOrFail($validated['room_id']);

        if (! $room->is_available) {
            return back()
                ->withErrors([
                    'start_date' => 'Ruangan sudah dibooking pada rentang waktu yang dipilih.',
                ])
                ->with('error', 'Ruangan sudah dibooking pada rentang waktu yang dipilih.')
                ->withInput();
        }

        if ($this->roomHasScheduleConflict($room, $schedulePayload)) {
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
            ->where('role', User::ROLE_ADMIN_BAP)
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
     * Return availability information for a room on a specific date or date range.
     */
    public function availability(Request $request, Room $room)
    {
        if (! $room->is_available) {
            return response()->json([
                'available' => false,
                'bookings' => [],
                'daily_bookings' => [],
                'message' => 'Ruangan saat ini ditandai tidak tersedia.',
            ]);
        }

        $date = $request->query('date');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (! $date && ! $startDate && ! $endDate) {
            return response()->json([
                'available' => true,
                'bookings' => [],
                'daily_bookings' => [],
            ], 200);
        }

        try {
            $day = $date ? Carbon::parse($date) : null;
            $rangeStart = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
            $rangeEnd = $endDate ? Carbon::parse($endDate)->endOfDay() : null;
        } catch (\Throwable $exception) {
            return response()->json([
                'available' => true,
                'bookings' => [],
                'daily_bookings' => [],
            ], 422);
        }

        if ($rangeStart && $rangeEnd && $rangeStart->gt($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
        }

        $queryStart = $rangeStart ?? $day?->copy()->startOfDay();
        $queryEnd = $rangeEnd ?? $day?->copy()->endOfDay();

        if (! $queryStart || ! $queryEnd) {
            return response()->json([
                'available' => true,
                'bookings' => [],
                'daily_bookings' => [],
            ], 200);
        }

        $bookings = $room->bookings()
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->get();

        $dailyBookings = $this->buildDailyBookings($bookings, $queryStart, $queryEnd);
        $selectedDateEntry = $day
            ? collect($dailyBookings)->firstWhere('date', $day->toDateString())
            : null;
        $bookingsForSelectedDate = $selectedDateEntry['bookings'] ?? [];

        return response()->json([
            'available' => true,
            'bookings' => $bookingsForSelectedDate,
            'daily_bookings' => $dailyBookings,
        ], 200);
    }

    /**
     * Build room booking intervals grouped per day within the requested range.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDailyBookings($bookings, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $grouped = collect();

        foreach ($bookings as $booking) {
            foreach ($booking->buildDailyIntervalsWithinRange($rangeStart, $rangeEnd) as $interval) {
                $grouped->push($interval);
            }
        }

        return $grouped
            ->groupBy('date')
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'bookings' => $items
                        ->sortBy(['start', 'end'])
                        ->values()
                        ->map(fn ($item) => [
                            'id' => $item['id'],
                            'title' => $item['title'],
                            'status' => $item['status'],
                            'start' => $item['start'],
                            'end' => $item['end'],
                        ])
                        ->all(),
                ];
            })
            ->sortBy('date')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function buildSchedulePayload(array $validated): array
    {
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $startDateTime = Carbon::parse($validated['start_date'].' '.$validated['start_time']);
        $endDateTime = Carbon::parse($validated['end_date'].' '.$validated['end_time']);

        return [
            'schedule_mode' => $validated['schedule_mode'],
            'start_time' => $startDateTime->toDateTimeString(),
            'end_time' => $endDateTime->toDateTimeString(),
            'schedule_start_date' => $startDate->toDateString(),
            'schedule_end_date' => $endDate->toDateString(),
            'schedule_start_clock' => $startDateTime->format('H:i:s'),
            'schedule_end_clock' => $endDateTime->format('H:i:s'),
        ];
    }

    /**
     * @param  array<string, string>  $schedulePayload
     */
    private function roomHasScheduleConflict(Room $room, array $schedulePayload): bool
    {
        $requestedIntervals = $this->buildRequestedDailyIntervals($schedulePayload);

        if ($requestedIntervals === []) {
            return false;
        }

        $rangeStart = Carbon::parse($schedulePayload['schedule_start_date'])->startOfDay();
        $rangeEnd = Carbon::parse($schedulePayload['schedule_end_date'])->endOfDay();

        $existingBookings = $room->bookings()
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->get();

        foreach ($existingBookings as $booking) {
            $existingIntervals = $booking->buildDailyIntervalsWithinRange($rangeStart, $rangeEnd);

            foreach ($requestedIntervals as $requestedInterval) {
                foreach ($existingIntervals as $existingInterval) {
                    if (($requestedInterval['date'] ?? null) !== ($existingInterval['date'] ?? null)) {
                        continue;
                    }

                    if ($this->intervalsOverlap($requestedInterval, $existingInterval)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $schedulePayload
     * @return array<int, array<string, string>>
     */
    private function buildRequestedDailyIntervals(array $schedulePayload): array
    {
        $booking = new Booking([
            'id' => 0,
            'title' => 'Requested booking',
            'status' => 'waiting',
            'schedule_mode' => $schedulePayload['schedule_mode'],
            'start_time' => $schedulePayload['start_time'],
            'end_time' => $schedulePayload['end_time'],
            'schedule_start_date' => $schedulePayload['schedule_start_date'],
            'schedule_end_date' => $schedulePayload['schedule_end_date'],
            'schedule_start_clock' => $schedulePayload['schedule_start_clock'],
            'schedule_end_clock' => $schedulePayload['schedule_end_clock'],
        ]);

        return $booking->buildDailyIntervalsWithinRange(
            Carbon::parse($schedulePayload['schedule_start_date'])->startOfDay(),
            Carbon::parse($schedulePayload['schedule_end_date'])->endOfDay(),
        );
    }

    /**
     * @param  array<string, string|int>  $left
     * @param  array<string, string|int>  $right
     */
    private function intervalsOverlap(array $left, array $right): bool
    {
        $date = (string) $left['date'];
        $leftStart = Carbon::parse($date.' '.((string) $left['start']));
        $leftEnd = Carbon::parse($date.' '.((string) $left['end']));
        $rightStart = Carbon::parse($date.' '.((string) $right['start']));
        $rightEnd = Carbon::parse($date.' '.((string) $right['end']));

        return $leftStart->lt($rightEnd) && $leftEnd->gt($rightStart);
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

        $pdf = Pdf::loadView('pdf.booking-letter', [
            'booking' => $booking,
            'generatedAt' => now(),
            'approvedAt' => $approvedAt,
        ])->setPaper('a4');

        $filename = 'Surat-Peminjaman-Ruangan-' . $booking->id . '.pdf';

        return $pdf->download($filename);
    }

}
