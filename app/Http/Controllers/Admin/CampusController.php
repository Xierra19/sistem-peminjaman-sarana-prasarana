<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampusController extends Controller
{
    /**
     * Tampilkan daftar campus.
     */
    public function index()
    {
        $campuses = Campus::orderBy('name')->get();

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
        $campus->delete();

        return redirect()->route('admin.campus.index')->with('success', 'Campus berhasil dihapus!');
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