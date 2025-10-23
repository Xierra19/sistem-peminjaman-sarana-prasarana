<?php

namespace App\Http\Controllers;

use App\Http\Requests\Semester\StoreSemesterRequest;
use App\Http\Requests\Semester\UpdateSemesterRequest;
use App\Models\MasterSemester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller
{
    public function index(): Response
    {
        $semesters = MasterSemester::query()
            ->orderByDesc('year')
            ->orderBy('term')
            ->get();

        return Inertia::render('Admin/Semesters/Index', [
            'semesters' => $semesters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Semesters/Form', [
            'semester' => new MasterSemester(),
            'mode' => 'create',
        ]);
    }

    public function store(StoreSemesterRequest $request): RedirectResponse
    {
        MasterSemester::create($request->validated());

        return redirect()->route('admin.semesters.index')->with('status', 'Semester berhasil dibuat.');
    }

    public function edit(MasterSemester $semester): Response
    {
        $semesterPayload = [
            'id' => $semester->id,
            'year' => $semester->year,
            'term' => $semester->term,
            'is_active' => (bool) $semester->is_active,
            'anchor_date' => optional($semester->anchor_date)->format('Y-m-d'),
            'start_date' => optional($semester->start_date)->format('Y-m-d'),
            'end_date' => optional($semester->end_date)->format('Y-m-d'),
            'uts_week' => $semester->uts_week,
            'uas_week' => $semester->uas_week,
        ];

        return Inertia::render('Admin/Semesters/Form', [
            'semester' => $semesterPayload,
            'mode' => 'edit',
        ]);
    }

    public function update(UpdateSemesterRequest $request, MasterSemester $semester): RedirectResponse
    {
        $semester->update($request->validated());

        return redirect()->route('admin.semesters.index')->with('status', 'Semester berhasil diperbarui.');
    }

    public function destroy(MasterSemester $semester): RedirectResponse
    {
        $semester->delete();

        return redirect()->route('admin.semesters.index')->with('status', 'Semester berhasil dihapus.');
    }

    public function toggleActive(Request $request, MasterSemester $semester): RedirectResponse
    {
        $semester->update([
            'is_active' => ! $semester->is_active,
        ]);

        return redirect()->route('admin.semesters.index')->with('status', 'Status aktif semester diperbarui.');
    }
}
