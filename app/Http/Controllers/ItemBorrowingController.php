<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMultipleItemBorrowingRequest;
use App\Http\Requests\UpdateMultipleItemBorrowingRequest;
use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingItem;
use App\Models\ItemBorrowingLog;
use App\Models\User;
use App\Notifications\ItemBorrowingRequestedNotification;
use App\Services\ItemAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ItemBorrowingController extends Controller
{
    public function index()
    {
        $itemBorrowings = ItemBorrowing::with([
            'items.item',
            'singleItem', // legacy
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ])
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('ItemBorrowings/Index', [
            'itemBorrowings' => $itemBorrowings,
        ]);
    }

    public function create()
    {
        $items = Item::query()
            ->select('id', 'code', 'name', 'category', 'quantity', 'is_available')
            ->orderBy('name')
            ->get();

        return Inertia::render('ItemBorrowings/Create', [
            'items' => $items,
        ]);
    }

    public function edit(ItemBorrowing $itemBorrowing)
    {
        if ($itemBorrowing->user_id !== Auth::id() || !in_array($itemBorrowing->status, ['rejected', 'waiting'])) {
            abort(403, 'Tidak dapat mengedit request ini.');
        }

        $items = Item::query()
            ->select('id', 'code', 'name', 'category', 'quantity', 'is_available')
            ->orderBy('name')
            ->get();

        // Legacy single to array for form
        $formItems = $itemBorrowing->items->map(fn($pivot) => [
            'id' => $pivot->id,
            'item_id' => $pivot->item_id,
            'quantity' => $pivot->quantity,
            'borrow_date' => $pivot->borrow_date->format('Y-m-d'),
            'return_date' => $pivot->return_date->format('Y-m-d'),
        ]);

        if ($itemBorrowing->singleItem && $formItems->isEmpty()) {
            $formItems->push([
                'item_id' => $itemBorrowing->singleItem->id,
                'quantity' => $itemBorrowing->quantity,
                'borrow_date' => $itemBorrowing->borrow_date->format('Y-m-d'),
                'return_date' => $itemBorrowing->return_date->format('Y-m-d'),
            ]);
        }

        return Inertia::render('ItemBorrowings/Edit', [
            'itemBorrowing' => $itemBorrowing,
            'items' => $items,
            'formItems' => $formItems,
        ]);
    }


    public function store(StoreMultipleItemBorrowingRequest $request, ItemAvailabilityService $availabilityService)
    {
        $validated = $request->validated();
        $itemsData = $validated['items'];

        // Pre-validate availability for all items
        $errors = [];
        foreach ($itemsData as $index => $itemData) {
            $item = Item::findOrFail($itemData['item_id']);
            
            if (! $item->is_available) {
                $errors["items.{$index}.item_id"] = 'Barang sedang tidak tersedia untuk dipinjam.';
                continue;
            }

            $borrowDate = Carbon::parse($itemData['borrow_date'])->startOfDay();
            $returnDate = Carbon::parse($itemData['return_date'])->endOfDay();
            
            if ($availabilityService->hasEnoughStock($item, $borrowDate, $returnDate, (int) $itemData['quantity'])) {
                continue;
            }

            $availability = $availabilityService->getAvailability($item, $borrowDate, $returnDate);
            $errors["items.{$index}.quantity"] = 'Stok tidak mencukupi. Sisa tersedia: ' . $availability['remaining_quantity'];
        }

        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        // Store attachment
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('item-borrowing-attachments', 'public');
        }

        $itemBorrowing = DB::transaction(function () use ($validated, $attachmentPath) {
            // Create main borrowing record
            $borrowing = ItemBorrowing::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'attachment' => $attachmentPath,
                'user_id' => Auth::id(),
                'status' => 'waiting',
                // Legacy fields for old schema compatibility
                'item_id' => null,
                'quantity' => 0,
                'borrow_date' => null,
                'return_date' => null,
            ]);

            // Create pivot items (deduplicate by item_id)
            $deduped = collect($validated['items'])
                ->keyBy('item_id')
                ->values();

            foreach ($deduped as $itemData) {
                ItemBorrowingItem::create([
                    'item_borrowing_id' => $borrowing->id,
                    'item_id'           => $itemData['item_id'],
                    'quantity'          => $itemData['quantity'],
                    'borrow_date'       => Carbon::parse($itemData['borrow_date']),
                    'return_date'       => Carbon::parse($itemData['return_date']),
                ]);
            }

            // Log request
            ItemBorrowingLog::create([
                'item_borrowing_id' => $borrowing->id,
                'user_id' => Auth::id(),
                'action' => 'requested',
                'description' => 'Peminjaman ' . count($validated['items']) . ' jenis barang diajukan oleh pengguna.',
            ]);

            return $borrowing;
        });

        $itemBorrowing->load(['user', 'items.item']);

        // Notify admins
        $admins = User::whereIn('role', User::itemAdminRoles())
            ->whereNotNull('email')
            ->get();

        if ($admins->isNotEmpty()) {
            try {
                Notification::send($admins, new ItemBorrowingRequestedNotification($itemBorrowing));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('item-borrowings.index')
            ->with('success', 'Permintaan peminjaman ' . count($itemsData) . ' jenis barang berhasil dibuat dan menunggu persetujuan.');
    }

    public function update(UpdateMultipleItemBorrowingRequest $request, ItemBorrowing $itemBorrowing, ItemAvailabilityService $availabilityService)
    {
        if ($itemBorrowing->user_id !== Auth::id() || !in_array($itemBorrowing->status, ['rejected', 'waiting'])) {
            abort(403);
        }

        $validated = $request->validated();

        // Pre-validate new availability
        $errors = [];
        foreach ($validated['items'] as $index => $itemData) {
            $item = Item::findOrFail($itemData['item_id']);
            $borrowDate = Carbon::parse($itemData['borrow_date'])->startOfDay();
            $returnDate = Carbon::parse($itemData['return_date'])->endOfDay();

            $exclude = isset($itemData['id']) ? ItemBorrowingItem::find($itemData['id']) : null;
            
            if (!$availabilityService->hasEnoughStock($item, $borrowDate, $returnDate, (int) $itemData['quantity'], $exclude)) {
                $availability = $availabilityService->getAvailability($item, $borrowDate, $returnDate, $exclude);
                $errors["items.{$index}.quantity"] = 'Stok tidak mencukupi. Sisa: ' . $availability['remaining_quantity'];
            }
        }

        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        DB::transaction(function () use ($validated, $itemBorrowing, $request) {
            // Update main record
            $updateData = [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ];

            if ($request->hasFile('attachment')) {
                // Delete old if exists
                if ($itemBorrowing->attachment && Storage::disk('public')->exists($itemBorrowing->attachment)) {
                    Storage::disk('public')->delete($itemBorrowing->attachment);
                }
                $updateData['attachment'] = $request->file('attachment')->store('item-borrowing-attachments', 'public');
            }

            $updateData = array_merge($updateData, [
                // Ensure legacy fields remain null for multi-item
                'item_id' => null,
                'quantity' => 0,
                'borrow_date' => null,
                'return_date' => null,
            ]);
            $itemBorrowing->update($updateData);

            // Sync items (delete old, create/update new)
            $existingIds = collect($validated['items'])->pluck('id')->filter()->toArray();
            ItemBorrowingItem::where('item_borrowing_id', $itemBorrowing->id)
                ->whereNotIn('id', $existingIds)
                ->delete();

            foreach ($validated['items'] as $itemData) {
                $pivotData = [
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'borrow_date' => Carbon::parse($itemData['borrow_date']),
                    'return_date' => Carbon::parse($itemData['return_date']),
                ];

                if (isset($itemData['id'])) {
                    ItemBorrowingItem::find($itemData['id'])->update($pivotData);
                } else {
                    $pivotData['item_borrowing_id'] = $itemBorrowing->id;
                    ItemBorrowingItem::create($pivotData);
                }
            }
        });

        return redirect()
            ->route('item-borrowings.index')
            ->with('success', 'Request peminjaman berhasil direvisi dan menunggu persetujuan ulang.');
    }


    public function show(ItemBorrowing $itemBorrowing)
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $itemBorrowing->user_id !== $user->id) {
            abort(403);
        }

        $itemBorrowing->load([
            'items.item',
            'singleItem', // legacy
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ]);

        $latestDecisionLog = $itemBorrowing->logs
            ->filter(fn (ItemBorrowingLog $log) => in_array($log->action, ['approved', 'rejected', 'cancelled', 'returned'], true))
            ->last();

        return Inertia::render('ItemBorrowings/Show', [
            'itemBorrowing' => $itemBorrowing,
            'latestDecisionLog' => $latestDecisionLog,
        ]);
    }


    public function availability(Request $request, Item $item, ItemAvailabilityService $availabilityService)
    {
        if (! $item->is_available) {
            return response()->json([
                'available' => false,
                'total_quantity' => (int) $item->quantity,
                'reserved_quantity' => 0,
                'remaining_quantity' => 0,
                'borrowings' => [],
                'message' => 'Barang saat ini ditandai tidak tersedia.',
            ]);
        }

        $request->validate([
            'borrow_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:borrow_date',
        ]);

        $borrowDate = $request->query('borrow_date');
        $returnDate = $request->query('return_date');

        try {
            $start = Carbon::parse($borrowDate)->startOfDay();
            $end = Carbon::parse($returnDate)->endOfDay();
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Rentang tanggal tidak valid.',
            ], 422);
        }

        if ($end->lt($start)) {
            return response()->json([
                'message' => 'Tanggal kembali harus sama atau setelah tanggal pinjam.',
            ], 422);
        }

        return response()->json(
            $availabilityService->getAvailability($item, $start, $end)
        , 200);
    }

    public function downloadAttachment(ItemBorrowing $itemBorrowing)
    {
        $user = Auth::user();

        if (! $itemBorrowing->attachment) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (! $user->isAdmin() && $itemBorrowing->user_id !== $user->id) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($itemBorrowing->attachment)) {
            abort(404, 'File lampiran tidak tersedia.');
        }

        return response()->download(
            Storage::disk('public')->path($itemBorrowing->attachment),
            basename($itemBorrowing->attachment)
        );
    }

    public function downloadSignedLetter(ItemBorrowing $itemBorrowing)
    {
        $user = Auth::user();

        if (! $itemBorrowing->signed_letter) {
            abort(404, 'Surat yang ditandatangani tidak ditemukan.');
        }

        if (! $user->isAdmin() && $itemBorrowing->user_id !== $user->id) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($itemBorrowing->signed_letter)) {
            abort(404, 'File surat tidak tersedia.');
        }

        return response()->download(
            Storage::disk('public')->path($itemBorrowing->signed_letter),
            basename($itemBorrowing->signed_letter)
        );
    }

    public function cancel(ItemBorrowing $itemBorrowing)
    {
        if ($itemBorrowing->user_id !== Auth::id()) {
            abort(403);
        }

        if (! in_array($itemBorrowing->status, ['waiting', 'requested'], true)) {
            return redirect()
                ->back()
                ->with('error', 'Permintaan tidak dapat dibatalkan karena sudah diproses oleh admin.');
        }

        DB::transaction(function () use ($itemBorrowing): void {
            $itemBorrowing->update([
                'status' => 'cancelled',
            ]);

            ItemBorrowingLog::create([
                'item_borrowing_id' => $itemBorrowing->id,
                'user_id' => Auth::id(),
                'action' => 'cancelled',
                'description' => 'Peminjaman barang dibatalkan oleh pemohon.',
            ]);
        });

        return redirect()
            ->back()
            ->with('success', 'Permintaan peminjaman barang berhasil dibatalkan.');
    }
}
