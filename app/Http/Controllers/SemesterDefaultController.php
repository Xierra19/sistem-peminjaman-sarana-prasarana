<?php

namespace App\Http\Controllers;

use App\Http\Requests\SemesterDefault\StoreDefaultRequest;
use App\Http\Requests\SemesterDefault\UpdateDefaultRequest;
use App\Models\MasterSemester;
use App\Models\Room;
use App\Models\SemesterCourseDefault;
use App\Services\Semesters\ExactDuplicateChecker;
use App\Services\Semesters\OverlapChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class SemesterDefaultController extends Controller
{
    public function __construct(
        protected ExactDuplicateChecker $duplicateChecker,
        protected OverlapChecker $overlapChecker
    ) {
    }

    public function index(MasterSemester $semester): Response
    {
        $raw = $semester->courseDefaults()
            ->with(['theoryRoom', 'practicum1Room', 'practicum2Room'])
            ->orderBy('day_of_week')
            ->orderBy('theory_start_time')
            ->get();

        $defaults = $raw->map(function (SemesterCourseDefault $d) {
            $theoryStart = $this->formatTimeValue($d->theory_start_time);
            $theoryEnd = $this->formatTimeValue($d->theory_end_time);
            $prac1Start = $this->formatTimeValue($d->practicum1_start_time);
            $prac1End = $this->formatTimeValue($d->practicum1_end_time);
            $prac2Start = $this->formatTimeValue($d->practicum2_start_time);
            $prac2End = $this->formatTimeValue($d->practicum2_end_time);
            return [
                'id' => $d->id,
                'course_name' => $d->course_name,
                'course_code' => $d->course_code,
                'day_of_week' => $d->day_of_week,
                'theory_start_time' => $theoryStart,
                'theory_end_time' => $theoryEnd,
                'practicum1_start_time' => $prac1Start,
                'practicum1_end_time' => $prac1End,
                'practicum2_start_time' => $prac2Start,
                'practicum2_end_time' => $prac2End,
                'theory_room' => $d->theoryRoom ? ['id' => $d->theoryRoom->id, 'name' => $d->theoryRoom->name] : null,
                'practicum1_room' => $d->practicum1Room ? ['id' => $d->practicum1Room->id, 'name' => $d->practicum1Room->name] : null,
                'practicum2_room' => $d->practicum2Room ? ['id' => $d->practicum2Room->id, 'name' => $d->practicum2Room->name] : null,
            ];
        });

        $semesterPayload = [
            'id' => $semester->id,
            'year' => $semester->year,
            'term' => $semester->term,
        ];

        return Inertia::render('Admin/Semesters/Defaults/Index', [
            'semester' => $semesterPayload,
            'defaults' => $defaults,
            'rooms' => Inertia::lazy(fn () => Room::orderBy('name')->get(['id', 'name'])),
        ]);
    }

    public function create(MasterSemester $semester): Response
    {
        $semesterPayload = [
            'id' => $semester->id,
            'year' => $semester->year,
            'term' => $semester->term,
        ];

        return Inertia::render('Admin/Semesters/Defaults/Form', [
            'semester' => $semesterPayload,
            'defaultItem' => [
                'course_name' => '',
                'course_code' => '',
                'day_of_week' => '',
                'theory_start_time' => '',
                'theory_end_time' => '',
                'theory_room_id' => '',
                'practicum1_start_time' => '',
                'practicum1_end_time' => '',
                'practicum1_room_id' => '',
                'practicum2_start_time' => '',
                'practicum2_end_time' => '',
                'practicum2_room_id' => '',
            ],
            'rooms' => Room::orderBy('name')->get(['id','name']),
            'mode' => 'create',
        ]);
    }

    public function store(StoreDefaultRequest $request, MasterSemester $semester): RedirectResponse
    {
        $data = $this->prepareData($request->validated());
        $data['semester_id'] = $semester->id;

        if ($message = $this->checkDuplicate($semester->id, $data)) {
            return back()->withInput()->withErrors($message)->with('error', reset($message));
        }

        if ($message = $this->checkOverlap($semester->id, $data)) {
            return back()->withInput()->withErrors($message)->with('error', reset($message));
        }

        SemesterCourseDefault::create($data);

        return redirect()
            ->route('admin.semesters.defaults.index', $semester)
            ->with('success', 'Default jadwal berhasil dibuat.');
    }

    public function edit(MasterSemester $semester, SemesterCourseDefault $default): Response
    {
        $semesterPayload = [
            'id' => $semester->id,
            'year' => $semester->year,
            'term' => $semester->term,
        ];

        $defaultPayload = [
            'id' => $default->id,
            'course_name' => $default->course_name,
            'course_code' => $default->course_code,
            'day_of_week' => $default->day_of_week,
            'theory_start_time' => $this->formatTimeValue($default->theory_start_time) ?? '',
            'theory_end_time' => $this->formatTimeValue($default->theory_end_time) ?? '',
            'theory_room_id' => $default->theory_room_id,
            'practicum1_start_time' => $this->formatTimeValue($default->practicum1_start_time) ?? '',
            'practicum1_end_time' => $this->formatTimeValue($default->practicum1_end_time) ?? '',
            'practicum1_room_id' => $default->practicum1_room_id ?? '',
            'practicum2_start_time' => $this->formatTimeValue($default->practicum2_start_time) ?? '',
            'practicum2_end_time' => $this->formatTimeValue($default->practicum2_end_time) ?? '',
            'practicum2_room_id' => $default->practicum2_room_id ?? '',
        ];

        return Inertia::render('Admin/Semesters/Defaults/Form', [
            'semester' => $semesterPayload,
            'defaultItem' => $defaultPayload,
            'rooms' => Room::orderBy('name')->get(['id','name']),
            'mode' => 'edit',
        ]);
    }

    public function update(UpdateDefaultRequest $request, MasterSemester $semester, SemesterCourseDefault $default): RedirectResponse
    {
        $data = $this->prepareData($request->validated());

        if ($message = $this->checkDuplicate($semester->id, $data, $default->id)) {
            return back()->withInput()->withErrors($message)->with('error', reset($message));
        }

        if ($message = $this->checkOverlap($semester->id, $data, $default->id)) {
            return back()->withInput()->withErrors($message)->with('error', reset($message));
        }

        $default->update($data);

        return redirect()
            ->route('admin.semesters.defaults.index', $semester)
            ->with('success', 'Default jadwal berhasil diperbarui.');
    }

    public function destroy(MasterSemester $semester, SemesterCourseDefault $default): RedirectResponse
    {
        $default->delete();

        return redirect()
            ->route('admin.semesters.defaults.index', $semester)
            ->with('success', 'Default jadwal berhasil dihapus.');
    }

    /**
     * @param array<string,mixed> $data
     */
    private function prepareData(array $data): array
    {
        $data['practicum1_start_time'] = $data['practicum1_start_time'] ?? null;
        $data['practicum1_end_time'] = $data['practicum1_end_time'] ?? null;
        $data['practicum2_start_time'] = $data['practicum2_start_time'] ?? null;
        $data['practicum2_end_time'] = $data['practicum2_end_time'] ?? null;

        return $data;
    }

    private function formatTimeValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
                $parts = explode(':', $value);
                $hour = str_pad((string) ((int) $parts[0]), 2, '0', STR_PAD_LEFT);
                $minute = $parts[1] ?? '00';

                return $hour.':'.$minute;
            }

            $parsed = date_create($value);
            if ($parsed instanceof \DateTimeInterface) {
                return $parsed->format('H:i');
            }
        }

        return null;
    }

    private function checkDuplicate(int $semesterId, array $data, ?int $excludeId = null): ?array
    {
        $duplicate = $this->duplicateChecker->check($semesterId, $data, $excludeId);
        if (! $duplicate) {
            return null;
        }

        $message = sprintf(
            'Baris duplikat: %s dengan room yang sama di hari & jam yang sama sudah ada (course_code: %s).',
            $duplicate->slotType,
            $duplicate->record->course_code
        );

        $key = match ($duplicate->slotType) {
            'Teori' => 'theory_start_time',
            'Praktikum 1' => 'practicum1_start_time',
            'Praktikum 2' => 'practicum2_start_time',
            default => 'course_code',
        };

        return [$key => $message];
    }

    private function checkOverlap(int $semesterId, array $data, ?int $excludeId = null): ?array
    {
        $slotMap = [
            'Teori' => ['room' => 'theory_room_id', 'start' => 'theory_start_time', 'end' => 'theory_end_time'],
            'Praktikum 1' => ['room' => 'practicum1_room_id', 'start' => 'practicum1_start_time', 'end' => 'practicum1_end_time'],
            'Praktikum 2' => ['room' => 'practicum2_room_id', 'start' => 'practicum2_start_time', 'end' => 'practicum2_end_time'],
        ];

        foreach ($slotMap as $label => $config) {
            $roomId = $data[$config['room']] ?? null;
            $start = $data[$config['start']] ?? null;
            $end = $data[$config['end']] ?? null;

            if (! $roomId || ! $start || ! $end) {
                continue;
            }

            $conflict = $this->overlapChecker->checkRoomOverlap($semesterId, $roomId, $data['day_of_week'], $start, $end, $excludeId);
            if ($conflict) {
                $roomName = match ($conflict->slotType) {
                    'Teori' => optional($conflict->record->theoryRoom)->name,
                    'Praktikum 1' => optional($conflict->record->practicum1Room)->name,
                    'Praktikum 2' => optional($conflict->record->practicum2Room)->name,
                    default => null,
                };

                $message = sprintf(
                    'Bentrok: Ruang %s pada %s %s-%s sudah dipakai oleh %s (ID #%d).',
                    $roomName ?? 'ID '.$roomId,
                    $data['day_of_week'],
                    $start,
                    $end,
                    $conflict->record->course_code.' / '.$conflict->record->course_name,
                    $conflict->record->id
                );

                $key = $config['start'];

                return [$key => $message];
            }
        }

        return null;
    }
}
