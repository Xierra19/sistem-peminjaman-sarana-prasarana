<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BuildingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $buildings = Building::with(['campus'])->withCount('rooms')->orderBy('name')->get();

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
        if ($building->rooms()->exists()) {
            return redirect()
                ->route('admin.buildings.index')
                ->with('error', 'Gedung tidak dapat dihapus karena masih memiliki ruangan terkait. Hapus ruangan terlebih dahulu.');
        }

        $building->delete();

        return redirect()
            ->route('admin.buildings.index')
            ->with('success', 'Building berhasil dihapus!');
    }

    /**
     * Hapus banyak building sekaligus.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:buildings,id'],
        ]);

        $buildings = Building::query()
            ->withCount('rooms')
            ->whereIn('id', $validated['ids'])
            ->get();

        $deletableIds = $buildings
            ->filter(fn (Building $building) => (int) $building->rooms_count === 0)
            ->pluck('id')
            ->values();

        $blockedCount = $buildings->count() - $deletableIds->count();

        if ($deletableIds->isEmpty()) {
            return redirect()
                ->route('admin.buildings.index')
                ->with('error', 'Tidak ada gedung yang dapat dihapus karena masih memiliki ruangan terkait.');
        }

        DB::transaction(function () use ($deletableIds) {
            Building::whereIn('id', $deletableIds)->delete();
        });

        $message = 'Berhasil menghapus ' . $deletableIds->count() . ' gedung.';

        if ($blockedCount > 0) {
            $message .= ' ' . $blockedCount . ' gedung lain dilewati karena masih memiliki ruangan terkait.';
        }

        return redirect()
            ->route('admin.buildings.index')
            ->with('success', $message);
    }
}
