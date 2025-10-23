<?php

namespace App\Http\Controllers;

use App\Http\Requests\Semester\StoreSemesterRequest;
use App\Http\Requests\Semester\UpdateSemesterRequest;
use App\Models\MasterSemester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SemesterController extends Controller
{
    public function index(): View
    {
        $semesters = MasterSemester::query()
            ->orderByDesc('year')
            ->orderBy('term')
            ->get();

        return view('semesters.index', compact('semesters'));
    }

    public function create(): View
    {
        return view('semesters.form', [
            'semester' => new MasterSemester(),
            'mode' => 'create',
        ]);
    }

    public function store(StoreSemesterRequest $request): RedirectResponse
    {
        MasterSemester::create($request->validated());

        return redirect()->route('admin.semesters.index')->with('status', 'Semester berhasil dibuat.');
    }

    public function edit(MasterSemester $semester): View
    {
        return view('semesters.form', [
            'semester' => $semester,
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