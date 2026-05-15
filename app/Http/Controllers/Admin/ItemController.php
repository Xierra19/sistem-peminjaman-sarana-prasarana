<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('name')->get();

        return Inertia::render('Admin/Items/Index', [
            'items' => $items
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:items'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0'],
            'is_available' => ['required', 'boolean'],
        ]);

        Item::create($validated);

        return redirect()->route('admin.items.index')->with('success', 'Barang berhasil ditambahkan!');
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('items')->ignore($item->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0'],
            'is_available' => ['required', 'boolean'],
        ]);

        $item->update($validated);

        return redirect()->route('admin.items.index')->with('success', 'Barang berhasil diupdate!');
    }

    public function destroy(Item $item)
    {
        // Check if item has any borrowing records
        if ($item->borrowingItems()->exists()) {
            return redirect()->route('admin.items.index')->with('error', 'Barang tidak dapat dihapus karena masih memiliki riwayat peminjaman!');
        }

        $item->delete();

        return redirect()->route('admin.items.index')->with('success', 'Barang berhasil dihapus!');
    }
}
