<?php

// file: app/Http/Controllers/Admin/SemesterController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SemesterUpdateRequest;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller
{
    /**
     * Show the semester edit form.
     */
    public function edit(): Response
    {
        $semester = Semester::query()->active()->first()
            ?? Semester::query()->orderByDesc('start_date')->orderByDesc('id')->first();

        if (!$semester) {
            $semester = Semester::query()->create();
        }

        return Inertia::render('Admin/Semester/Edit', [
            'semester' => [
                'id' => $semester->id,
                'start_date' => $semester->start_date?->toDateString(),
                'end_date' => $semester->end_date?->toDateString(),
                'uts_start_date' => $semester->uts_start_date?->toDateString(),
                'uts_end_date' => $semester->uts_end_date?->toDateString(),
                'uas_start_date' => $semester->uas_start_date?->toDateString(),
                'uas_end_date' => $semester->uas_end_date?->toDateString(),
                'teaching_weeks_before_uts' => $semester->teaching_weeks_before_uts,
                'teaching_weeks_after_uts' => $semester->teaching_weeks_after_uts,
                'is_active' => (bool) $semester->is_active,
            ],
        ]);
    }

    /**
     * Update the semester details.
     */
    public function update(SemesterUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $semesterId = $request->input('id') ?? $request->input('semester_id');

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

            if (($data['is_active'] ?? false) && $semester->exists) {
                Semester::query()
                    ->whereKeyNot($semester->getKey())
                    ->update(['is_active' => false]);
            }
        });

        return redirect()
            ->route('admin.semester.edit')
            ->with('success', 'Semester updated successfully.');
    }
}
