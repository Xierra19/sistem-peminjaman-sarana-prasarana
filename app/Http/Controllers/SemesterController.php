<?php

namespace App\Http\Controllers;

use App\Http\Requests\Semester\StoreSemesterRequest;
use App\Http\Requests\Semester\UpdateSemesterRequest;
use App\Models\MasterSemester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function edit(MasterSemester $semester): RedirectResponse
    {
        return redirect()->route('admin.semester.edit', ['semester' => $semester->id]);
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
