<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CampusController extends Controller
{
    /**
     * Tampilkan daftar campus.
     */
    public function index()
    {
        $campuses = Campus::withCount('buildings')->orderBy('name')->get();

        return Inertia::render('Admin/Campus/Index', [
            'campuses' => $campuses
        ]);
    }

    /**
     * Tampilkan form tambah campus.
     */
    public function create()
    {
        return Inertia::render('Admin/Campus/Create');
    }

    /**
     * Simpan campus baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\\+62\\d{8,13}|0\\d{8,13})$/'],
        ]);

        $validated['name'] = trim($validated['name']);
        $normalizedName = $this->normalizeName($validated['name']);

        $duplicateCampus = Campus::query()
            ->whereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$normalizedName])
            ->exists();

        if ($duplicateCampus) {
            return back()
                ->withErrors(['name' => 'Nama campus sudah digunakan.'])
                ->with('error', 'Nama campus sudah digunakan.');
        }

        Campus::create($validated);

        return redirect()->route('admin.campus.index')->with('success', 'Campus berhasil ditambahkan!');
    }

    /**
     * Tampilkan form edit.
     */
    public function edit(Campus $campus)
    {
        return Inertia::render('Admin/Campus/Edit', [
            'campus' => $campus
        ]);
    }

    /**
     * Update data campus.
     */
    public function update(Request $request, Campus $campus)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\\+62\\d{8,13}|0\\d{8,13})$/'],
        ]);

        $validated['name'] = trim($validated['name']);
        $normalizedName = $this->normalizeName($validated['name']);

        $duplicateCampus = Campus::query()
            ->where('id', '!=', $campus->id)
            ->whereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$normalizedName])
            ->exists();

        if ($duplicateCampus) {
            return back()
                ->withErrors(['name' => 'Nama campus sudah digunakan.'])
                ->with('error', 'Nama campus sudah digunakan.');
        }

        $campus->update($validated);

        return redirect()->route('admin.campus.index')->with('success', 'Campus berhasil diupdate!');
    }

    /**
     * Hapus data campus.
     */
    public function destroy(Campus $campus)
    {
        if ($campus->buildings()->exists()) {
            return redirect()->route('admin.campus.index')
                ->with('error', 'Campus tidak dapat dihapus karena masih memiliki gedung terkait. Hapus gedung terlebih dahulu.');
        }

        $campus->delete();

        return redirect()->route('admin.campus.index')->with('success', 'Campus berhasil dihapus!');
    }

    /**
     * Hapus banyak campus sekaligus.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:campuses,id'],
        ]);

        $campuses = Campus::query()
            ->withCount('buildings')
            ->whereIn('id', $validated['ids'])
            ->get();

        $deletableIds = $campuses
            ->filter(fn (Campus $campus) => (int) $campus->buildings_count === 0)
            ->pluck('id')
            ->values();

        $blockedCount = $campuses->count() - $deletableIds->count();

        if ($deletableIds->isEmpty()) {
            return redirect()
                ->route('admin.campus.index')
                ->with('error', 'Tidak ada campus yang dapat dihapus karena masih memiliki gedung terkait.');
        }

        DB::transaction(function () use ($deletableIds) {
            Campus::whereIn('id', $deletableIds)->delete();
        });

        $message = 'Berhasil menghapus ' . $deletableIds->count() . ' campus.';

        if ($blockedCount > 0) {
            $message .= ' ' . $blockedCount . ' campus lain dilewati karena masih memiliki gedung terkait.';
        }

        return redirect()
            ->route('admin.campus.index')
            ->with('success', $message);
    }

    /**
     * Normalize nama campus untuk perbandingan duplikasi
     * (lowercase dan hapus spasi)
     */
    protected function normalizeName(string $name): string
    {
        return strtolower(str_replace(' ', '', $name));
    }
}