<?php

namespace App\Http\Controllers;

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
        // Ambil data dari database
        $buildings = Building::with('campus')->get();
        $campuses = Campus::all();

        return Inertia::render('Admin/BuildingManagement', [
            'buildings' => $buildings,
            'campuses' => $campuses,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Logika untuk menyimpan data building baru
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campus_id' => 'required|exists:campuses,id',
        ]);

        Building::create($validated);

        return redirect()->back()->with('success', 'Building added successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Building $building)
    {
        // Logika untuk mengupdate data building
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campus_id' => 'required|exists:campuses,id',
        ]);

        $building->update($validated);

        return redirect()->back()->with('success', 'Building updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Building $building)
    {
        // Logika untuk menghapus data building
        $building->delete();

        return redirect()->back()->with('success', 'Building deleted successfully.');
    }
}