<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Campus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BuildingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $buildings = Building::with('campus')->orderBy('name')->get();

        return Inertia::render('Admin/Buildings/Index', [
            'buildings' => $buildings,
            'campuses'  => Inertia::lazy(fn () =>
                Campus::select('id', 'name')->orderBy('name')->get()
            ),
        ]);
    }

    /**
     * Show the form for creating a new building.
     */
    public function create()
    {
        $campuses = Campus::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Buildings/Create', [
            'campuses' => $campuses,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'campus_id' => ['required', 'exists:campuses,id'],
        ]);

        $validated['name'] = trim($validated['name']);
        $normalizedName = $this->normalizeName($validated['name']); // gunakan method dari parent

        $duplicateBuilding = Building::query()
            ->where('campus_id', $validated['campus_id'])
            ->whereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$normalizedName])
            ->exists();

        if ($duplicateBuilding) {
            return back()
                ->withErrors(['name' => 'Nama gedung sudah digunakan di kampus ini.'])
                ->with('error', 'Nama gedung sudah digunakan di kampus ini.');
        }

        Building::create($validated);

        return redirect()
            ->route('admin.buildings.index')
            ->with('success', 'Building berhasil ditambahkan!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Building $building)
    {
        $campuses = Campus::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Buildings/Edit', [
            'building' => $building->load('campus'),
            'campuses' => $campuses,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Building $building)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'campus_id' => ['required', 'exists:campuses,id'],
        ]);

        $validated['name'] = trim($validated['name']);
        $normalizedName = $this->normalizeName($validated['name']); // gunakan method dari parent

        $duplicateBuilding = Building::query()
            ->where('campus_id', $validated['campus_id'])
            ->where('id', '!=', $building->id)
            ->whereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$normalizedName])
            ->exists();

        if ($duplicateBuilding) {
            return back()
                ->withErrors(['name' => 'Nama gedung sudah digunakan di kampus ini.'])
                ->with('error', 'Nama gedung sudah digunakan di kampus ini.');
        }

        $building->update($validated);

        return redirect()
            ->route('admin.buildings.index')
            ->with('success', 'Building berhasil diupdate!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Building $building)
    {
        $building->delete();

        return redirect()
            ->route('admin.buildings.index')
            ->with('success', 'Building berhasil dihapus!');
    }
}
