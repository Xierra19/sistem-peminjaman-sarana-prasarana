<?php

// file: app/Http/Controllers/Admin/SemesterController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SemesterUpdateRequest;
use App\Models\CourseExamSchedule;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller
{
    /**
     * Show the semester edit form.
     */
    public function edit(Request $request): Response
    {
        $requestedId = $request->query('semester');

        $semester = null;

        if ($requestedId) {
            $semester = Semester::query()->find($requestedId);
        }

        if (!$semester) {
            $semester = Semester::query()->active()->first()
                ?? Semester::query()->orderByDesc('start_date')->orderByDesc('id')->first();
        }

        if (!$semester) {
            $semester = Semester::query()->create();
        }

        $semesters = Semester::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Semester $item) use ($semester) {
                $labelParts = array_filter([
                    $item->name ?: null,
                    $item->start_date ? $item->start_date->format('Y') : null,
                    ($item->start_date && $item->end_date)
                        ? $item->start_date->format('d M') . ' - ' . $item->end_date->format('d M Y')
                        : null,
                    $item->is_active ? 'aktif' : null,
                ]);

                $label = implode(' | ', $labelParts);

                if ($label === '') {
                    $label = 'Semester #' . $item->id;
                }

                return [
                    'id' => $item->id,
                    'label' => $label,
                    'is_current' => $item->id === $semester->id,
                ];
            });

        $offerings = CourseOffering::query()
            ->where('semester_id', $semester->id)
            ->with(['courseExamSchedules.room.building'])
            ->orderBy('course_code')
            ->get()
            ->map(function (CourseOffering $offering) {
                $exams = $offering->courseExamSchedules->keyBy('exam_type');

                $formatExam = static function (?CourseExamSchedule $exam): ?array {
                    if (!$exam) {
                        return null;
                    }

                    $room = $exam->room;
                    $roomExists = $room !== null;
                    $roomHasBuilding = $roomExists && $room->relationLoaded('building') && $room->building !== null;
                    $buildingName = $roomHasBuilding ? $room->building->name : null;

                    return [
                        'exam_date' => $exam->exam_date ? $exam->exam_date->toDateString() : null,
                        'start_time' => $exam->start_time,
                        'end_time' => $exam->end_time,
                        'room_id' => $roomExists ? $room->id : null,
                        'room_label' => $roomExists
                            ? ($buildingName ? "{$room->name} • {$buildingName}" : $room->name)
                            : null,
                    ];
                };

                return [
                    'id' => $offering->id,
                    'course_code' => $offering->course_code,
                    'course_name' => $offering->course_name,
                    'exams' => [
                        'uts' => $formatExam($exams->get('UTS')),
                        'uas' => $formatExam($exams->get('UAS')),
                    ],
                ];
            });

        $rooms = Room::query()
            ->with('building:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (Room $room) {
                $buildingName = ($room->relationLoaded('building') && $room->building)
                    ? $room->building->name
                    : null;

                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'building' => $buildingName,
                    'label' => $buildingName ? "{$room->name} • {$buildingName}" : $room->name,
                ];
            });

        return Inertia::render('Admin/Semester/Edit', [
            'semester' => [
                'id' => $semester->id,
                'start_date' => $semester->start_date ? $semester->start_date->toDateString() : null,
                'end_date' => $semester->end_date ? $semester->end_date->toDateString() : null,
                'teaching_1_7_start_date' => $semester->teaching_1_7_start_date
                    ? $semester->teaching_1_7_start_date->toDateString()
                    : null,
                'teaching_1_7_end_date' => $semester->teaching_1_7_end_date
                    ? $semester->teaching_1_7_end_date->toDateString()
                    : null,
                'teaching_8_14_start_date' => $semester->teaching_8_14_start_date
                    ? $semester->teaching_8_14_start_date->toDateString()
                    : null,
                'teaching_8_14_end_date' => $semester->teaching_8_14_end_date
                    ? $semester->teaching_8_14_end_date->toDateString()
                    : null,
                'uts_start_date' => $semester->uts_start_date ? $semester->uts_start_date->toDateString() : null,
                'uts_end_date' => $semester->uts_end_date ? $semester->uts_end_date->toDateString() : null,
                'uas_start_date' => $semester->uas_start_date ? $semester->uas_start_date->toDateString() : null,
                'uas_end_date' => $semester->uas_end_date ? $semester->uas_end_date->toDateString() : null,
                'is_active' => (bool) $semester->is_active,
            ],
            'semesters' => $semesters,
            'dateRangeStrings' => $semester->dateRangeStrings(),
            'offerings' => $offerings,
            'rooms' => $rooms,
        ]);
    }

    /**
     * Update the semester details.
     */
    public function update(SemesterUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $semesterId = $request->input('id') ?: $request->input('semester_id');

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        $semester = Semester::query()->find($semesterId)
            ?? Semester::query()->active()->first()
            ?? Semester::query()->orderByDesc('start_date')->orderByDesc('id')->first()
            ?? new Semester();

        DB::transaction(function () use ($semester, $data) {
            $semester->fill($data);
            $semester->save();

            if (
                ($data['is_active'] ?? false)
                && $semester->exists
                && Schema::hasColumn($semester->getTable(), 'is_active')
            ) {
                Semester::query()
                    ->whereKeyNot($semester->getKey())
                    ->update(['is_active' => false]);
            }
        });

        return redirect()
            ->back()
            ->with('success', 'Semester updated.');
    }
}
