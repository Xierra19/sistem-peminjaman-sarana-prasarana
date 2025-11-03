<?php

// file: app/Http/Controllers/Admin/CourseOfferingController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseOfferingDetailResource;
use App\Http\Resources\CourseOfferingResource;
use App\Models\CourseExamSchedule;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CourseOfferingController extends Controller
{
    public function index(Semester $semester): Response
    {
        $offerings = CourseOffering::query()
            ->where('semester_id', $semester->id)
            ->with(['courseExamSchedules'])
            ->withCount(['courseMeetings as meetings_count' => function ($query) {
                $query->whereNotNull('meeting_date');
            }])
            ->orderBy('course_code')
            ->orderBy('class_group')
            ->get();

        return Inertia::render('Admin/Offerings/Index', [
            'semesterId' => $semester->id,
            'offerings' => CourseOfferingResource::collection($offerings)->resolve(),
        ]);
    }

    public function show(CourseOffering $offering): Response
    {
        $offering->loadMissing(['semester', 'courseMeetings.room', 'courseExamSchedules.room']);

        return Inertia::render('Admin/Offerings/Show', CourseOfferingDetailResource::make($offering)->resolve());
    }

    public function updateExam(CourseOffering $offering, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exam_type' => ['required', Rule::in(['UTS', 'UAS'])],
            'exam_date' => ['nullable', 'date_format:Y-m-d'],
            'room_code' => ['nullable', 'string'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $roomId = null;
        $roomCode = trim((string) ($data['room_code'] ?? ''));

        $offering->loadMissing('semester');

        if ($roomCode !== '') {
            $room = Room::query()->where('code', $roomCode)->first();

            if (!$room) {
                throw ValidationException::withMessages([
                    'room_code' => "Room code '{$roomCode}' not found.",
                ]);
            }

            $roomId = $room->id;
        }

        $examDate = $data['exam_date'] ?? null;
        $weekSeq = $examDate
            ? CourseExamSchedule::computeWeekSeqFor($offering->semester, $examDate, $data['exam_type'])
            : null;

        CourseExamSchedule::query()->updateOrCreate(
            [
                'course_offering_id' => $offering->id,
                'exam_type' => $data['exam_type'],
            ],
            [
                'exam_date' => $examDate,
                'week_seq' => $weekSeq,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'room_id' => $roomId,
            ]
        );

        return redirect()
            ->back()
            ->with('status', 'Exam schedule updated.');
    }
}
