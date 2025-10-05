<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomController extends Controller
{
    /**
     * Display a listing of the rooms.
     */
    public function index()
    {
        $rooms = Room::with('building.campus')->orderBy('name')->get();
        $buildings = Building::with('campus')->orderBy('name')->get();

        return Inertia::render('Admin/Rooms/Index', [
            'rooms' => $rooms,
            'buildings' => $buildings,
        ]);
    }

    /**
     * Show the form for creating a new room.
     */
    public function create()
    {
        $buildings = Building::with('campus')->orderBy('name')->get();

        return Inertia::render('Admin/Rooms/Create', [
            'buildings' => $buildings,
        ]);
    }

    /**
     * Store a newly created room in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'building_id' => ['required', 'exists:buildings,id'],
            'capacity' => ['required', 'integer', 'min:1'],
        ]);

        Room::create($validated);

        return redirect()->route('admin.rooms.index')->with('success', 'Room berhasil ditambahkan!');
    }

    /**
     * Show the form for editing the specified room.
     */
    public function edit(Room $room)
    {
        $buildings = Building::with('campus')->orderBy('name')->get();

        return Inertia::render('Admin/Rooms/Edit', [
            'room' => $room->load('building.campus'),
            'buildings' => $buildings,
        ]);
    }

    /**
     * Update the specified room in storage.
     */
    public function update(Request $request, Room $room)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'building_id' => ['required', 'exists:buildings,id'],
            'capacity' => ['required', 'integer', 'min:1'],
        ]);

        $room->update($validated);

        return redirect()->route('admin.rooms.index')->with('success', 'Room berhasil diupdate!');
    }

    /**
     * Remove the specified room from storage.
     */
    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()->route('admin.rooms.index')->with('success', 'Room berhasil dihapus!');
    }
}