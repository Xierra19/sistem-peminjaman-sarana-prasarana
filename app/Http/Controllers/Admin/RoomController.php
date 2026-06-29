<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RoomController extends Controller
{
    /**
     * Display a listing of the rooms.
     */
    public function index()
    {
        $rooms = Room::with('building.campus')->withCount('bookings')->orderBy('name')->get();

        return Inertia::render('Admin/Rooms/Index', [
            'rooms' => $rooms,
            'buildings' => Inertia::lazy(fn () =>
                Building::with('campus:id,name')
                    ->select('id', 'name', 'campus_id')
                    ->orderBy('name')
                    ->get()
            ),
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
            'is_available' => ['required', 'boolean'],
        ]);

        $validated['name'] = trim($validated['name']);
        $validated['is_available'] = (bool) $validated['is_available'];
        $normalizedName = $this->normalizeName($validated['name']);

        $duplicateRoom = Room::query()
            ->where('building_id', $validated['building_id'])
            ->whereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$normalizedName])
            ->exists();

        if ($duplicateRoom) {
            throw ValidationException::withMessages([
                'name' => 'Nama ruangan sudah digunakan di gedung dan kampus ini.',
            ]);
        }

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
            'is_available' => ['required', 'boolean'],
        ]);

        $validated['name'] = trim($validated['name']);
        $validated['is_available'] = (bool) $validated['is_available'];
        $normalizedName = $this->normalizeName($validated['name']);

        $duplicateRoom = Room::query()
            ->where('building_id', $validated['building_id'])
            ->where('id', '!=', $room->id)
            ->whereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$normalizedName])
            ->exists();

        if ($duplicateRoom) {
            throw ValidationException::withMessages([
                'name' => 'Nama ruangan sudah digunakan di gedung dan kampus ini.',
            ]);
        }

        $room->update($validated);

        return redirect()->route('admin.rooms.index')->with('success', 'Room berhasil diupdate!');
    }

    /**
     * Remove the specified room from storage.
     */
    public function destroy(Room $room)
    {
        if ($room->bookings()->exists()) {
            return redirect()
                ->route('admin.rooms.index')
                ->with('error', 'Ruangan tidak dapat dihapus karena masih memiliki booking terkait. Selesaikan atau pindahkan booking terlebih dahulu.');
        }

        $room->delete();

        return redirect()->route('admin.rooms.index')->with('success', 'Room berhasil dihapus!');
    }
}