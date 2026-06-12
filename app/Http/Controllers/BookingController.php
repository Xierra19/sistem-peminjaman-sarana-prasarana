<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingRoomSchedule;
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
use Illuminate\Validation\ValidationException;

use Inertia\Inertia;
use App\Notifications\BookingRequestedNotification;
use App\Services\ExpirePendingBookings;

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

        $query = Booking::query()
            ->with([
                'room:id,name,building_id',
                'room.building:id,name,campus_id',
                'room.building.campus:id,name',
                'roomSchedules.room.building.campus',
            ])
            ->join('rooms', 'bookings.room_id', '=', 'rooms.id')
            ->join('buildings', 'rooms.building_id', '=', 'buildings.id')
            ->join('campuses', 'buildings.campus_id', '=', 'campuses.id')
            ->select('bookings.*')
            ->where('bookings.user_id', Auth::id());

        // Filter pencarian (server-side)
        $query->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('bookings.title', 'like', "%{$search}%")
                    ->orWhere('bookings.description', 'like', "%{$search}%")
                    ->orWhere('rooms.name', 'like', "%{$search}%")
                    ->orWhere('buildings.name', 'like', "%{$search}%")
                    ->orWhere('campuses.name', 'like', "%{$search}%")
                    ->orWhereHas('roomSchedules.room', function ($roomQuery) use ($search) {
                        $roomQuery->where('name', 'like', "%{$search}%");
                    });
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
            'roomSchedules.room.building.campus',
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ]);

        $latestDecisionLog = $booking->logs
            ->filter(fn (LogHistory $log) => in_array($log->action, ['approved', 'rejected', 'cancelled', 'expired'], true))
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
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'schedules' => 'required|array|min:1|max:20',
            'schedules.*.room_id' => 'required|exists:rooms,id',
            'schedules.*.date' => 'required|date|after_or_equal:'.$minimumStartDate->toDateString(),
            'schedules.*.start_time' => 'required|date_format:H:i',
            'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,png|max:2048',
        ], [
            'schedules.required' => 'Tambahkan minimal satu jadwal ruangan.',
            'schedules.*.date.after_or_equal' => 'Tanggal penggunaan minimal '.$minStartDateLabel.' (H+3 dari hari ini).',
        ]);

        $schedules = collect($validated['schedules'])
            ->map(fn (array $schedule, int $index) => [
                'input_index' => $index,
                'room_id' => (int) $schedule['room_id'],
                'start_time' => Carbon::parse($schedule['date'].' '.$schedule['start_time'])->toDateTimeString(),
                'end_time' => Carbon::parse($schedule['date'].' '.$schedule['end_time'])->toDateTimeString(),
            ])
            ->values();

        foreach ($schedules->groupBy('room_id') as $roomSchedules) {
            $orderedSchedules = $roomSchedules->sortBy('start_time')->values();

            for ($index = 1; $index < $orderedSchedules->count(); $index++) {
                $previous = $orderedSchedules[$index - 1];
                $current = $orderedSchedules[$index];

                if (Carbon::parse($current['start_time'])->lt(Carbon::parse($previous['end_time']))) {
                    throw ValidationException::withMessages([
                        "schedules.{$current['input_index']}.start_time" => 'Jadwal ruangan ini bertumpuk dengan baris lain dalam pengajuan yang sama.',
                    ]);
                }
            }
        }

        $attachment = $request->file('attachment');

        $booking = DB::transaction(function () use ($validated, $schedules, $attachment): Booking {
            $rooms = Room::query()
                ->whereIn('id', $schedules->pluck('room_id')->unique()->sort()->values())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($schedules as $schedule) {
                $room = $rooms->get($schedule['room_id']);
                $inputIndex = $schedule['input_index'];

                if (! $room?->is_available) {
                    throw ValidationException::withMessages([
                        "schedules.{$inputIndex}.room_id" => 'Ruangan sedang ditandai tidak tersedia.',
                    ]);
                }

                if ($this->roomHasScheduleConflict($schedule)) {
                    throw ValidationException::withMessages([
                        "schedules.{$inputIndex}.room_id" => 'Ruangan sudah digunakan pada tanggal dan jam yang dipilih.',
                    ]);
                }
            }

            $orderedSchedules = $schedules->sortBy('start_time')->values();
            $firstSchedule = $orderedSchedules->first();
            $lastEnd = $schedules->max('end_time');
            $start = Carbon::parse($schedules->min('start_time'));
            $end = Carbon::parse($lastEnd);
            $attachmentPath = $attachment?->store('attachments', 'public');

            $booking = Booking::query()->create([
                'room_id' => $firstSchedule['room_id'],
                'user_id' => Auth::id(),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => 'waiting',
                'attachment' => $attachmentPath,
                'start_time' => $start->toDateTimeString(),
                'end_time' => $end->toDateTimeString(),
                'schedule_mode' => Booking::MODE_CONTINUOUS,
                'schedule_start_date' => $start->toDateString(),
                'schedule_end_date' => $end->toDateString(),
                'schedule_start_clock' => $start->format('H:i:s'),
                'schedule_end_clock' => $end->format('H:i:s'),
            ]);

            $booking->roomSchedules()->createMany(
                $orderedSchedules
                    ->map(fn (array $schedule) => collect($schedule)->except('input_index')->all())
                    ->all()
            );

            LogHistory::query()->create([
                'booking_id' => $booking->id,
                'user_id' => Auth::id(),
                'action' => 'requested',
                'description' => 'Booking diajukan oleh pengguna.',
            ]);

            return $booking;
        });

        $booking->load(['user', 'roomSchedules.room.building.campus']);

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

        $schedules = $room->bookingSchedules()
            ->with('booking:id,title,status')
            ->whereHas('booking', function ($query): void {
                $query->whereNotIn('status', ['rejected', 'cancelled', 'expired']);
            })
            ->where('start_time', '<', $queryEnd)
            ->where('end_time', '>', $queryStart)
            ->get();

        $legacyBookings = $room->bookings()
            ->whereDoesntHave('roomSchedules')
            ->whereNotIn('status', ['rejected', 'cancelled', 'expired'])
            ->get();

        $dailyBookings = $this->buildDailyBookings(
            $schedules,
            $legacyBookings,
            $queryStart,
            $queryEnd,
        );
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
    private function buildDailyBookings(
        $schedules,
        $legacyBookings,
        Carbon $rangeStart,
        Carbon $rangeEnd,
    ): array
    {
        $grouped = collect();

        foreach ($schedules as $schedule) {
            foreach ($schedule->buildDailyIntervalsWithinRange($rangeStart, $rangeEnd) as $interval) {
                $grouped->push($interval);
            }
        }

        foreach ($legacyBookings as $booking) {
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
     * @param  array{room_id: int, start_time: string, end_time: string}  $schedule
     */
    private function roomHasScheduleConflict(array $schedule): bool
    {
        $hasDetailConflict = BookingRoomSchedule::query()
            ->where('room_id', $schedule['room_id'])
            ->where('start_time', '<', $schedule['end_time'])
            ->where('end_time', '>', $schedule['start_time'])
            ->whereHas('booking', function ($query): void {
                $query->whereNotIn('status', ['rejected', 'cancelled', 'expired']);
            })
            ->exists();

        if ($hasDetailConflict) {
            return true;
        }

        return Booking::query()
            ->where('room_id', $schedule['room_id'])
            ->whereDoesntHave('roomSchedules')
            ->whereNotIn('status', ['rejected', 'cancelled', 'expired'])
            ->where('start_time', '<', $schedule['end_time'])
            ->where('end_time', '>', $schedule['start_time'])
            ->exists();
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

    public function cancel(Booking $booking, ExpirePendingBookings $expirePendingBookings)
    {
        $user = Auth::user();

        if ($booking->user_id !== $user->id) {
            abort(403);
        }

        if ($expirePendingBookings->expireIfPastDue($booking)) {
            return redirect()->back()->with(
                'error',
                'Permintaan sudah kedaluwarsa karena hari peminjaman terakhir telah berakhir.'
            );
        }

        $cancellableStatuses = ['waiting', 'pending', 'requested'];

        if (! in_array($booking->status, $cancellableStatuses, true)) {
            $message = match ($booking->status) {
                'cancelled' => 'Booking sudah dibatalkan sebelumnya.',
                'expired' => 'Permintaan sudah kedaluwarsa dan tidak dapat dibatalkan.',
                default => 'Booking tidak dapat dibatalkan karena sudah diproses oleh admin.',
            };

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

        $booking->load(['user', 'roomSchedules.room.building.campus']);
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
