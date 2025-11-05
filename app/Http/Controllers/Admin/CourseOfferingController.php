<?php

// file: app/Http/Controllers/Admin/CourseOfferingController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseExamUpdateRequest;
use App\Http\Requests\CourseStoreRequest;
use App\Models\CourseExamSchedule;
use App\Models\CourseOffering;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CourseOfferingController extends Controller
{
    public function index(Semester $semester): Response
    {
        $offerings = CourseOffering::query()
            ->where('semester_id', $semester->id)
            ->orderBy('course_code')
            ->get()
            ->map(fn (CourseOffering $offering) => [
                'id' => $offering->id,
                'course_code' => $offering->course_code,
                'course_name' => $offering->course_name,
            ]);

        return Inertia::render('Admin/Offerings/Index', [
            'semesterId' => $semester->id,
            'offerings' => $offerings,
        ]);
    }

    public function store(CourseStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        CourseOffering::query()->updateOrCreate(
            [
                'semester_id' => $data['semester_id'],
                'course_code' => $data['course_code'],
            ],
            [
                'course_name' => $data['course_name'],
            ]
        );

        return redirect()->back()->with('success', 'Course offering saved.');
    }

    public function updateExam(CourseOffering $offering, CourseExamUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        CourseExamSchedule::query()->updateOrCreate(
            [
                'course_offering_id' => $offering->id,
                'exam_type' => $data['exam_type'],
            ],
            [
                'exam_date' => $data['exam_date'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'room_id' => $data['room_id'] ?? null,
            ]
        );

        return redirect()
            ->back()
            ->with('success', "{$data['exam_type']} exam schedule saved.");
    }
}
