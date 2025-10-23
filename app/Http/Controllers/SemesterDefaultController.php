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

class SemesterDefaultController extends Controller
{
    public function __construct(
        protected ExactDuplicateChecker $duplicateChecker,
        protected OverlapChecker $overlapChecker
    ) {
    }

    public function index(MasterSemester $semester): View
    {
        $defaults = $semester->courseDefaults()
            ->with(['theoryRoom', 'practicum1Room', 'practicum2Room'])
            ->orderBy('day_of_week')
            ->orderBy('theory_start_time')
            ->get();

        return view('semesters.defaults.index', [
            'semester' => $semester,
            'defaults' => $defaults,
        ]);
    }

    public function create(MasterSemester $semester): View
    {
        return view('semesters.defaults.form', [
            'semester' => $semester,
            'default' => new SemesterCourseDefault(),
            'rooms' => Room::orderBy('name')->get(),
            'mode' => 'create',
        ]);
    }

    public function store(StoreDefaultRequest $request, MasterSemester $semester): RedirectResponse
    {
        $data = $this->prepareData($request->validated());
        $data['semester_id'] = $semester->id;

        if ($message = $this->checkDuplicate($semester->id, $data)) {
            return back()->withInput()->withErrors($message);
        }

        if ($message = $this->checkOverlap($semester->id, $data)) {
            return back()->withInput()->withErrors($message);
        }

        SemesterCourseDefault::create($data);

        return redirect()->route('admin.semesters.defaults.index', $semester)->with('status', 'Default jadwal berhasil dibuat.');
    }

    public function edit(MasterSemester $semester, SemesterCourseDefault $default): View
    {
        return view('semesters.defaults.form', [
            'semester' => $semester,
            'default' => $default,
            'rooms' => Room::orderBy('name')->get(),
            'mode' => 'edit',
        ]);
    }

    public function update(UpdateDefaultRequest $request, MasterSemester $semester, SemesterCourseDefault $default): RedirectResponse
    {
        $data = $this->prepareData($request->validated());

        if ($message = $this->checkDuplicate($semester->id, $data, $default->id)) {
            return back()->withInput()->withErrors($message);
        }

        if ($message = $this->checkOverlap($semester->id, $data, $default->id)) {
            return back()->withInput()->withErrors($message);
        }

        $default->update($data);

        return redirect()->route('admin.semesters.defaults.index', $semester)->with('status', 'Default jadwal berhasil diperbarui.');
    }

    public function destroy(MasterSemester $semester, SemesterCourseDefault $default): RedirectResponse
    {
        $default->delete();

        return redirect()->route('admin.semesters.defaults.index', $semester)->with('status', 'Default jadwal berhasil dihapus.');
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