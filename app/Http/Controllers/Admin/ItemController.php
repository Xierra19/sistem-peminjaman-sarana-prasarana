<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index()
    {
        $items = Item::withCount(['itemBorrowings', 'borrowingItems'])->orderBy('name')->get();

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
        if ($item->itemBorrowings()->exists() || $item->borrowingItems()->exists()) {
            return redirect()
                ->route('admin.items.index')
                ->with('error', 'Barang tidak dapat dihapus karena masih memiliki riwayat peminjaman. Selesaikan atau hapus data peminjaman terlebih dahulu.');
        }

        $item->delete();

        return redirect()->route('admin.items.index')->with('success', 'Barang berhasil dihapus!');
    }

    /**
     * Hapus banyak barang sekaligus.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:items,id'],
        ]);

        $items = Item::query()
            ->withCount(['itemBorrowings', 'borrowingItems'])
            ->whereIn('id', $validated['ids'])
            ->get();

        $deletableIds = $items
            ->filter(fn (Item $item) => (int) $item->item_borrowings_count === 0 && (int) $item->borrowing_items_count === 0)
            ->pluck('id')
            ->values();

        $blockedCount = $items->count() - $deletableIds->count();

        if ($deletableIds->isEmpty()) {
            return redirect()
                ->route('admin.items.index')
                ->with('error', 'Tidak ada barang yang dapat dihapus karena masih memiliki riwayat peminjaman.');
        }

        DB::transaction(function () use ($deletableIds) {
            Item::whereIn('id', $deletableIds)->delete();
        });

        $message = 'Berhasil menghapus ' . $deletableIds->count() . ' barang.';

        if ($blockedCount > 0) {
            $message .= ' ' . $blockedCount . ' barang lain dilewati karena masih memiliki riwayat peminjaman.';
        }

        return redirect()
            ->route('admin.items.index')
            ->with('success', $message);
    }
}
