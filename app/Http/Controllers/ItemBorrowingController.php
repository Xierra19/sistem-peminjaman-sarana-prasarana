<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemBorrowingRequest;
use App\Models\Item;
use App\Models\ItemBorrowing;
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

class ItemBorrowingController extends Controller
{
    public function index()
    {
        $itemBorrowings = ItemBorrowing::with([
            'item',
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

    public function store(StoreItemBorrowingRequest $request, ItemAvailabilityService $availabilityService)
    {
        $validated = $request->validated();
        $item = Item::query()->findOrFail($validated['item_id']);

        if (! $item->is_available) {
            return back()
                ->withErrors(['item_id' => 'Barang sedang tidak tersedia untuk dipinjam.'])
                ->with('error', 'Barang sedang tidak tersedia untuk dipinjam.')
                ->withInput();
        }

        if ((int) $validated['quantity'] > (int) $item->quantity) {
            return back()
                ->withErrors(['quantity' => 'Jumlah yang diminta melebihi stok total barang.'])
                ->with('error', 'Jumlah yang diminta melebihi stok total barang.')
                ->withInput();
        }

        $borrowDate = Carbon::parse($validated['borrow_date'])->startOfDay();
        $returnDate = Carbon::parse($validated['return_date'])->endOfDay();
        $availability = $availabilityService->getAvailability($item, $borrowDate, $returnDate);

        if ($availability['remaining_quantity'] < (int) $validated['quantity']) {
            return back()
                ->withErrors([
                    'quantity' => 'Stok tidak mencukupi pada rentang tanggal yang dipilih. Sisa stok tersedia: '.$availability['remaining_quantity'].'.',
                ])
                ->with('error', 'Stok tidak mencukupi pada rentang tanggal yang dipilih.')
                ->withInput();
        }

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('item-borrowing-attachments', 'public');
        }

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'waiting';
        $validated['borrow_date'] = $borrowDate;
        $validated['return_date'] = $returnDate;

        $itemBorrowing = DB::transaction(function () use ($validated): ItemBorrowing {
            $itemBorrowing = ItemBorrowing::create($validated);

            ItemBorrowingLog::create([
                'item_borrowing_id' => $itemBorrowing->id,
                'user_id' => Auth::id(),
                'action' => 'requested',
                'description' => 'Peminjaman barang diajukan oleh pengguna.',
            ]);

            return $itemBorrowing;
        });

        $itemBorrowing->load(['user', 'item']);

        $admins = User::query()
            ->whereIn('role', User::itemAdminRoles())
            ->whereNotNull('email')
            ->get();

        if ($admins->isNotEmpty()) {
            try {
                Notification::send($admins, new ItemBorrowingRequestedNotification($itemBorrowing));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()
            ->route('item-borrowings.index')
            ->with('success', 'Permintaan peminjaman barang berhasil dibuat dan menunggu persetujuan.');
    }

    public function show(ItemBorrowing $itemBorrowing)
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $itemBorrowing->user_id !== $user->id) {
            abort(403);
        }

        $itemBorrowing->load([
            'item',
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

        $borrowDate = $request->query('borrow_date');
        $returnDate = $request->query('return_date');

        if (! $borrowDate || ! $returnDate) {
            return response()->json([
                'available' => (int) $item->quantity > 0,
                'total_quantity' => (int) $item->quantity,
                'reserved_quantity' => 0,
                'remaining_quantity' => (int) $item->quantity,
                'borrowings' => [],
            ]);
        }

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
        );
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
