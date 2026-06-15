<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Campus;
use App\Models\LogHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Inertia\Inertia;
use App\Services\BookingCreationWorkflow;
use App\Services\BookingCancellationService;
use App\Services\RoomAvailabilityService;
use App\Support\CancellationResult;

class BookingController extends Controller
{
    /**
     * Display a listing of the authenticated user's booking requests.
     */
    public function index(Request $request)
    {
        $searchInput = $request->input('search');
        $statusInput = $request->input('status');
        $sortInput = $request->input('sort');
        $directionInput = $request->input('direction');
        $perPageInput = filter_var($request->input('per_page'), FILTER_VALIDATE_INT);

        $search = is_string($searchInput) && trim($searchInput) !== ''
            ? trim($searchInput)
            : null;
        $status = is_string($statusInput) && in_array($statusInput, [
            Booking::STATUS_WAITING,
            Booking::STATUS_APPROVED,
            Booking::STATUS_REJECTED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_EXPIRED,
        ], true)
            ? $statusInput
            : null;
        $sort = is_string($sortInput) && in_array($sortInput, [
            'number',
            'title',
            'room',
            'start_time',
            'end_time',
            'status',
            'created_at',
        ], true)
            ? $sortInput
            : 'created_at';
        $direction = $directionInput === 'asc' ? 'asc' : 'desc';
        $perPage = $perPageInput === false
            ? 10
            : max(1, min($perPageInput, 100));

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
            'number' => 'bookings.id',
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
        $this->authorize('view', $booking);

        $booking->load([
            'room.building.campus',
            'roomSchedules.room.building.campus',
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ]);

        $latestDecisionLog = $booking->logs
            ->filter(fn (LogHistory $log) => in_array($log->action, [
                Booking::STATUS_APPROVED,
                Booking::STATUS_REJECTED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_EXPIRED,
            ], true))
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
    public function store(StoreBookingRequest $request, BookingCreationWorkflow $workflow)
    {
        $workflow->create($request->validated(), $request->file('attachment'), $request->user());

        return redirect()->route('bookings.index')->with('success', 'Booking berhasil dibuat dan menunggu persetujuan.');
    }

    /**
     * Return availability information for a room on a specific date or date range.
     */
    public function availability(Request $request, Room $room, RoomAvailabilityService $availabilityService)
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
        $dates = $request->query('dates', []);

        if (! is_array($dates)) {
            $dates = [$dates];
        }

        if (! $date && ! $startDate && ! $endDate && empty($dates)) {
            return response()->json([
                'available' => true,
                'bookings' => [],
                'daily_bookings' => [],
            ], 200);
        }

        try {
            $day = $date ? Carbon::parse($date) : null;
            $selectedDates = collect($dates)
                ->filter(fn ($selectedDate) => is_string($selectedDate) && $selectedDate !== '')
                ->map(fn (string $selectedDate) => Carbon::parse($selectedDate)->startOfDay())
                ->unique(fn (Carbon $selectedDate) => $selectedDate->toDateString())
                ->sortBy(fn (Carbon $selectedDate) => $selectedDate->toDateString())
                ->values();
            $rangeStart = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
            $rangeEnd = $endDate ? Carbon::parse($endDate)->endOfDay() : null;
        } catch (\Throwable $exception) {
            return response()->json([
                'available' => true,
                'bookings' => [],
                'daily_bookings' => [],
            ], 422);
        }

        if ($selectedDates->count() > 20) {
            return response()->json([
                'available' => true,
                'bookings' => [],
                'daily_bookings' => [],
                'message' => 'Maksimal 20 tanggal dapat diperiksa sekaligus.',
            ], 422);
        }

        return response()->json(
            $availabilityService->getAvailability(
                $room,
                $day,
                $selectedDates,
                $rangeStart,
                $rangeEnd,
            ),
            200,
        );
    }

    public function downloadAttachment(Booking $booking)
    {
        $this->authorize('downloadAttachment', $booking);

        if (! $booking->attachment) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (! Storage::disk('public')->exists($booking->attachment)) {
            abort(404, 'File lampiran tidak tersedia.');
        }

        return response()->download(Storage::disk('public')->path($booking->attachment), basename($booking->attachment));
    }

    public function cancel(Booking $booking, BookingCancellationService $cancellationService)
    {
        $user = Auth::user();

        $this->authorize('cancel', $booking);

        $result = $cancellationService->cancel($booking, $user);

        if ($result !== CancellationResult::Cancelled) {
            $message = match ($result) {
                CancellationResult::AlreadyCancelled => 'Booking sudah dibatalkan sebelumnya.',
                CancellationResult::ExpiredNow => 'Permintaan sudah kedaluwarsa karena hari peminjaman terakhir telah berakhir.',
                CancellationResult::Expired => 'Permintaan sudah kedaluwarsa dan tidak dapat dibatalkan.',
                default => 'Booking tidak dapat dibatalkan karena sudah diproses oleh admin.',
            };

            return redirect()->back()->with('error', $message);
        }

        return redirect()->back()->with('success', 'Booking berhasil dibatalkan.');
    }

    public function downloadLetter(Booking $booking)
    {
        $this->authorize('downloadLetter', $booking);

        if ($booking->status !== Booking::STATUS_APPROVED) {
            abort(403, 'Booking belum disetujui.');
        }

        $booking->load(['user', 'roomSchedules.room.building.campus']);
        $approvedAt = $booking->logs()
            ->where('action', Booking::STATUS_APPROVED)
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
