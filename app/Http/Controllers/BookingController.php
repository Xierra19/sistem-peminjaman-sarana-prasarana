<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Campus;
use App\Models\LogHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

use Inertia\Inertia;
use App\Models\MasterSemester;
use App\Models\SemesterCourseDefault;
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

        // Validasi tambahan: bentrok dengan jadwal default semester aktif
        $activeSemester = MasterSemester::query()
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderBy('term')
            ->first();

        if ($activeSemester) {
            // Jika tanggal periode di-set, pastikan tanggal booking berada di dalamnya
            $withinPeriod = true;
            if ($activeSemester->start_date && $activeSemester->end_date) {
                $withinPeriod = $start->toDateString() >= $activeSemester->start_date->toDateString()
                    && $start->toDateString() <= $activeSemester->end_date->toDateString();
            }

            if ($withinPeriod) {
                // Peta hari ke bahasa Indonesia agar sesuai dengan enum DB
                $dayMap = [
                    0 => 'Minggu',
                    1 => 'Senin',
                    2 => 'Selasa',
                    3 => 'Rabu',
                    4 => 'Kamis',
                    5 => 'Jumat',
                    6 => 'Sabtu',
                ];
                $dayName = $dayMap[$start->dayOfWeek] ?? null;
                $roomId = (int) $validated['room_id'];
                $startStr = $start->format('H:i');
                $endStr = $end->format('H:i');

                $defaults = SemesterCourseDefault::query()
                    ->where('semester_id', $activeSemester->id)
                    ->where('day_of_week', $dayName)
                    ->where(function ($q) use ($roomId) {
                        $q->where('theory_room_id', $roomId)
                          ->orWhere('practicum1_room_id', $roomId)
                          ->orWhere('practicum2_room_id', $roomId);
                    })
                    ->get();

                $skipRegularSchedule = $this->isWithinExamWindow($activeSemester, $start);

                foreach ($defaults as $def) {
                    $examSlots = $this->examSlotsForDate($def, $start);

                    foreach ($examSlots as $slot) {
                        if ($slot['room_id'] === $roomId && $this->intervalsOverlap($startStr, $endStr, $slot['start'], $slot['end'])) {
                            return back()
                                ->withErrors(['start_time' => 'Bentrok dengan jadwal '.$slot['type'].' pada '.$slot['date'].' '.$slot['start'].'-'.$slot['end'].'.'])
                                ->with('error', 'Bentrok dengan jadwal '.$slot['type'].'.')
                                ->withInput();
                        }
                    }

                    $skipRegularForDefault = $skipRegularSchedule || ! empty($examSlots);

                    if (! $skipRegularForDefault && $def->theory_room_id === $roomId) {
                        $s = $this->parseTimeValue($def->theory_start_time);
                        $e = $this->parseTimeValue($def->theory_end_time);
                        if ($s && $e && $this->intervalsOverlap($startStr, $endStr, $s, $e)) {
                            return back()
                                ->withErrors(['start_time' => 'Bentrok dengan jadwal default (Teori) pada '.$def->day_of_week.' '.$s.'-'.$e.'.'])
                                ->with('error', 'Bentrok dengan jadwal default (Teori).')
                                ->withInput();
                        }
                    }
                    if (
                        ! $skipRegularForDefault
                        && $def->practicum1_room_id === $roomId
                        && $def->practicum1_start_time
                        && $def->practicum1_end_time
                    ) {
                        $s = $this->parseTimeValue($def->practicum1_start_time);
                        $e = $this->parseTimeValue($def->practicum1_end_time);
                        if ($s && $e && $this->intervalsOverlap($startStr, $endStr, $s, $e)) {
                            return back()
                                ->withErrors(['start_time' => 'Bentrok dengan jadwal default (Praktikum 1) pada '.$def->day_of_week.' '.$s.'-'.$e.'.'])
                                ->with('error', 'Bentrok dengan jadwal default (Praktikum 1).')
                                ->withInput();
                        }
                    }
                    if (
                        ! $skipRegularForDefault
                        && $def->practicum2_room_id === $roomId
                        && $def->practicum2_start_time
                        && $def->practicum2_end_time
                    ) {
                        $s = $this->parseTimeValue($def->practicum2_start_time);
                        $e = $this->parseTimeValue($def->practicum2_end_time);
                        if ($s && $e && $this->intervalsOverlap($startStr, $endStr, $s, $e)) {
                            return back()
                                ->withErrors(['start_time' => 'Bentrok dengan jadwal default (Praktikum 2) pada '.$def->day_of_week.' '.$s.'-'.$e.'.'])
                                ->with('error', 'Bentrok dengan jadwal default (Praktikum 2).')
                                ->withInput();
                        }
                    }
                }
            }
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
            ->where('role', 'admin')
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

        // Tambahkan blok waktu dari Jadwal Default Semester Aktif
        $defaultsIntervals = collect();
        $activeSemester = MasterSemester::query()
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderBy('term')
            ->first();

        if ($activeSemester) {
            $withinPeriod = true;
            if ($activeSemester->start_date && $activeSemester->end_date) {
                $withinPeriod = $day->toDateString() >= $activeSemester->start_date->toDateString()
                    && $day->toDateString() <= $activeSemester->end_date->toDateString();
            }

            if ($withinPeriod) {
                $dayMap = [
                    0 => 'Minggu',
                    1 => 'Senin',
                    2 => 'Selasa',
                    3 => 'Rabu',
                    4 => 'Kamis',
                    5 => 'Jumat',
                    6 => 'Sabtu',
                ];
                $dayName = $dayMap[$day->dayOfWeek] ?? null;

                if ($dayName) {
                    $defaults = SemesterCourseDefault::query()
                        ->where('semester_id', $activeSemester->id)
                        ->where('day_of_week', $dayName)
                        ->where(function ($q) use ($room) {
                            $q->where('theory_room_id', $room->id)
                              ->orWhere('practicum1_room_id', $room->id)
                              ->orWhere('practicum2_room_id', $room->id);
                        })
                        ->get();

                    $skipRegularSchedule = $this->isWithinExamWindow($activeSemester, $day);

                    foreach ($defaults as $def) {
                        $examSlots = $this->examSlotsForDate($def, $day);

                        foreach ($examSlots as $slot) {
                            if ($slot['room_id'] === $room->id) {
                                $defaultsIntervals->push([
                                    'id' => strtolower($slot['type']).'-'.$def->id,
                                    'title' => $slot['type'].' '.$def->course_code.' / '.$def->course_name,
                                    'status' => $slot['type'],
                                    'start' => $slot['start'],
                                    'end' => $slot['end'],
                                ]);
                            }
                        }

                        $skipRegularForDefault = $skipRegularSchedule || ! empty($examSlots);

                        if (! $skipRegularForDefault && $def->theory_room_id === $room->id) {
                            $s = $this->parseTimeValue($def->theory_start_time);
                            $e = $this->parseTimeValue($def->theory_end_time);
                            if ($s && $e) {
                                $defaultsIntervals->push([
                                    'id' => 'default-'.$def->id.'-theory',
                                    'title' => $def->course_code.' / '.$def->course_name,
                                    'status' => 'DEFAULT',
                                    'start' => $s,
                                    'end' => $e,
                                ]);
                            }
                        }
                        if (
                            ! $skipRegularForDefault
                            && $def->practicum1_room_id === $room->id
                            && $def->practicum1_start_time
                            && $def->practicum1_end_time
                        ) {
                            $s = $this->parseTimeValue($def->practicum1_start_time);
                            $e = $this->parseTimeValue($def->practicum1_end_time);
                            if ($s && $e) {
                                $defaultsIntervals->push([
                                    'id' => 'default-'.$def->id.'-prac1',
                                    'title' => $def->course_code.' / '.$def->course_name,
                                    'status' => 'DEFAULT',
                                    'start' => $s,
                                    'end' => $e,
                                ]);
                            }
                        }
                        if (
                            ! $skipRegularForDefault
                            && $def->practicum2_room_id === $room->id
                            && $def->practicum2_start_time
                            && $def->practicum2_end_time
                        ) {
                            $s = $this->parseTimeValue($def->practicum2_start_time);
                            $e = $this->parseTimeValue($def->practicum2_end_time);
                            if ($s && $e) {
                                $defaultsIntervals->push([
                                    'id' => 'default-'.$def->id.'-prac2',
                                    'title' => $def->course_code.' / '.$def->course_name,
                                    'status' => 'DEFAULT',
                                    'start' => $s,
                                    'end' => $e,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // Gabungkan dan urutkan berdasarkan jam mulai
        $allIntervals = $defaultsIntervals->concat($bookings)->sortBy('start')->values();

        return response()->json([
            'available' => true,
            'bookings' => $allIntervals,
        ]);
    }

    public function downloadAttachment(Booking $booking)
    {
        $user = Auth::user();

        if (! $booking->attachment) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($booking->attachment)) {
            abort(404, 'File lampiran tidak tersedia.');
        }

        return response()->download(Storage::disk('public')->path($booking->attachment), basename($booking->attachment));
    }

    public function downloadLetter(Booking $booking)
    {
        $user = Auth::user();

        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            abort(403);
        }

        if ($booking->status !== 'approved') {
            abort(403, 'Booking belum disetujui.');
        }

        $booking->load(['user', 'room.building.campus']);

        $pdf = Pdf::loadView('pdf.booking-letter', [
            'booking' => $booking,
            'generatedAt' => now(),
        ])->setPaper('a4');

        $filename = 'Surat-Peminjaman-Ruangan-' . $booking->id . '.pdf';

        return $pdf->download($filename);
    }

    private function isWithinExamWindow(?MasterSemester $semester, Carbon $date): bool
    {
        if (! $semester) {
            return false;
        }

        $day = $date->toDateString();

        if ($semester->uts_start_date && $semester->uts_end_date) {
            $start = $semester->uts_start_date->toDateString();
            $end = $semester->uts_end_date->toDateString();
            if ($day >= $start && $day <= $end) {
                return true;
            }
        }

        if ($semester->uas_start_date && $semester->uas_end_date) {
            $start = $semester->uas_start_date->toDateString();
            $end = $semester->uas_end_date->toDateString();
            if ($day >= $start && $day <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{type:string,date:string,start:string,end:string,room_id:int}>
     */
    private function examSlotsForDate(SemesterCourseDefault $default, Carbon $date): array
    {
        $dateString = $date->toDateString();
        $slots = [];

        foreach (['uts' => 'UTS', 'uas' => 'UAS'] as $key => $label) {
            $slot = $this->buildExamSlot($default, $key, $label);
            if ($slot && $slot['date'] === $dateString) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * @return array{type:string,date:string,start:string,end:string,room_id:int}|null
     */
    private function buildExamSlot(SemesterCourseDefault $default, string $type, string $label): ?array
    {
        $dateAttr = "{$type}_exam_date";
        $startAttr = "{$type}_start_time";
        $endAttr = "{$type}_end_time";
        $roomAttr = "{$type}_room_id";

        $date = $default->{$dateAttr};
        if (! $date) {
            return null;
        }

        $start = $this->parseTimeValue($default->{$startAttr});
        $end = $this->parseTimeValue($default->{$endAttr});

        if (! $start || ! $end) {
            return null;
        }

        $roomId = $default->{$roomAttr} ?? $default->theory_room_id ?? null;

        if (! $roomId) {
            return null;
        }

        return [
            'type' => $label,
            'date' => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date,
            'start' => $start,
            'end' => $end,
            'room_id' => (int) $roomId,
        ];
    }

    private function parseTimeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $string)) {
            $parts = explode(':', $string);
            $hour = str_pad((string) ((int) $parts[0]), 2, '0', STR_PAD_LEFT);
            $minute = $parts[1] ?? '00';

            return $hour.':'.$minute;
        }

        $parsed = date_create($string);

        return $parsed instanceof \DateTimeInterface ? $parsed->format('H:i') : null;
    }

    private function intervalsOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }
}
