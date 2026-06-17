<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMultipleItemBorrowingRequest;
use App\Http\Requests\UpdateMultipleItemBorrowingRequest;
use App\Models\Item;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingLog;
use App\Services\ItemAvailabilityService;
use App\Services\ItemBorrowingCancellationService;
use App\Services\ItemBorrowingPeriod;
use App\Services\ItemBorrowingWorkflow;
use App\Services\PublicFileStorage;
use App\Support\CancellationResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

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
        return Inertia::render('ItemBorrowings/Create', $this->itemFormProps());
    }

    public function resubmit(ItemBorrowing $itemBorrowing)
    {
        $this->authorize('resubmit', $itemBorrowing);
        $itemBorrowing->load(['items.item', 'singleItem', 'logs.user']);

        return Inertia::render('ItemBorrowings/Create', $this->itemFormProps($itemBorrowing));
    }

    private function itemFormProps(?ItemBorrowing $source = null): array
    {
        $items = Item::query()
            ->select('id', 'code', 'name', 'category', 'quantity', 'is_available')
            ->orderBy('name')
            ->get();

        return [
            'items' => $items,
            'minimumBorrowDate' => Carbon::now(config('app.business_timezone'))->addDays(7)->toDateString(),
            'sourceItemBorrowingId' => $source?->id,
            'initialData' => $source ? $this->itemBorrowingInitialData($source) : null,
            'revisionNote' => $source?->logs
                ->where('action', ItemBorrowing::STATUS_REJECTED)
                ->last()
                ?->description,
            'existingAttachment' => $source?->attachment,
        ];
    }

    private function itemBorrowingInitialData(ItemBorrowing $itemBorrowing): array
    {
        $formItems = $itemBorrowing->items->map(fn ($pivot) => [
            'item_id' => $pivot->item_id,
            'quantity' => $pivot->quantity,
            'borrow_date' => $pivot->borrow_date->timezone(config('app.business_timezone'))->format('Y-m-d'),
            'borrow_time' => $pivot->borrow_date->timezone(config('app.business_timezone'))->format('H:i'),
            'return_date' => $pivot->return_date->timezone(config('app.business_timezone'))->format('Y-m-d'),
            'return_time' => $pivot->return_date->timezone(config('app.business_timezone'))->format('H:i'),
        ]);

        if ($itemBorrowing->singleItem && $formItems->isEmpty()) {
            $formItems->push([
                'item_id' => $itemBorrowing->singleItem->id,
                'quantity' => $itemBorrowing->quantity,
                'borrow_date' => $itemBorrowing->borrow_date->timezone(config('app.business_timezone'))->format('Y-m-d'),
                'borrow_time' => $itemBorrowing->borrow_date->timezone(config('app.business_timezone'))->format('H:i'),
                'return_date' => $itemBorrowing->return_date->timezone(config('app.business_timezone'))->format('Y-m-d'),
                'return_time' => $itemBorrowing->return_date->timezone(config('app.business_timezone'))->format('H:i'),
            ]);
        }

        return [
            'title' => $itemBorrowing->title,
            'description' => $itemBorrowing->description,
            'items' => $formItems->values()->all(),
        ];
    }

    public function edit(ItemBorrowing $itemBorrowing)
    {
        $this->authorize('edit', $itemBorrowing);

        $items = Item::query()
            ->select('id', 'code', 'name', 'category', 'quantity', 'is_available')
            ->orderBy('name')
            ->get();

        // Legacy single to array for form
        $formItems = $itemBorrowing->items->map(fn ($pivot) => [
            'id' => $pivot->id,
            'item_id' => $pivot->item_id,
            'quantity' => $pivot->quantity,
            'borrow_date' => $pivot->borrow_date->timezone(config('app.business_timezone'))->format('Y-m-d'),
            'borrow_time' => $pivot->borrow_date->timezone(config('app.business_timezone'))->format('H:i'),
            'return_date' => $pivot->return_date->timezone(config('app.business_timezone'))->format('Y-m-d'),
            'return_time' => $pivot->return_date->timezone(config('app.business_timezone'))->format('H:i'),
        ]);

        if ($itemBorrowing->singleItem && $formItems->isEmpty()) {
            $formItems->push([
                'item_id' => $itemBorrowing->singleItem->id,
                'quantity' => $itemBorrowing->quantity,
                'borrow_date' => $itemBorrowing->borrow_date->timezone(config('app.business_timezone'))->format('Y-m-d'),
                'borrow_time' => $itemBorrowing->borrow_date->timezone(config('app.business_timezone'))->format('H:i'),
                'return_date' => $itemBorrowing->return_date->timezone(config('app.business_timezone'))->format('Y-m-d'),
                'return_time' => $itemBorrowing->return_date->timezone(config('app.business_timezone'))->format('H:i'),
            ]);
        }

        return Inertia::render('ItemBorrowings/Edit', [
            'itemBorrowing' => $itemBorrowing,
            'items' => $items,
            'formItems' => $formItems,
            'minimumBorrowDate' => $itemBorrowing->created_at
                ->copy()
                ->setTimezone(config('app.business_timezone'))
                ->startOfDay()
                ->addDays(7)
                ->max(Carbon::now(config('app.business_timezone'))->startOfDay())
                ->toDateString(),
            'revisionNote' => $itemBorrowing->logs
                ->where('action', ItemBorrowing::STATUS_NEEDS_REVISION)
                ->last()
                ?->description,
        ]);
    }

    public function store(
        StoreMultipleItemBorrowingRequest $request,
        ItemBorrowingWorkflow $workflow,
    ) {
        $validated = $request->validated();
        $workflow->create($validated, $request->file('attachment'), $request->user());

        return redirect()
            ->route('item-borrowings.index')
            ->with('success', 'Permintaan peminjaman '.count($validated['items']).' jenis barang berhasil dibuat dan menunggu persetujuan.');
    }

    public function update(
        UpdateMultipleItemBorrowingRequest $request,
        ItemBorrowing $itemBorrowing,
        ItemBorrowingWorkflow $workflow,
    ) {
        $workflow->update(
            $request->validated(),
            $itemBorrowing,
            $request->file('attachment'),
            $request->user(),
        );

        return redirect()
            ->route('item-borrowings.index')
            ->with('success', 'Request peminjaman berhasil direvisi dan menunggu persetujuan ulang.');
    }

    public function show(ItemBorrowing $itemBorrowing)
    {
        $this->authorize('view', $itemBorrowing);

        $itemBorrowing->load([
            'items.item',
            'singleItem', // legacy
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ]);

        $latestDecisionLog = $itemBorrowing->logs
            ->filter(fn (ItemBorrowingLog $log) => in_array($log->action, [
                ItemBorrowing::STATUS_APPROVED,
                ItemBorrowing::STATUS_NEEDS_REVISION,
                ItemBorrowing::STATUS_REJECTED,
                ItemBorrowing::STATUS_CANCELLED,
                ItemBorrowing::STATUS_RETURNED,
            ], true))
            ->last();

        return Inertia::render('ItemBorrowings/Show', [
            'itemBorrowing' => $itemBorrowing,
            'latestDecisionLog' => $latestDecisionLog,
        ]);
    }

    public function availability(
        Request $request,
        Item $item,
        ItemAvailabilityService $availabilityService,
        ItemBorrowingPeriod $period,
    ) {
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
            'borrow_date' => 'required|date_format:Y-m-d',
            'borrow_time' => 'required|date_format:H:i',
            'return_date' => 'required|date_format:Y-m-d',
            'return_time' => 'required|date_format:H:i',
            'exclude_item_borrowing_item_id' => 'nullable|integer',
        ]);

        try {
            [$start, $end] = $period->parse([
                'borrow_date' => $request->query('borrow_date'),
                'borrow_time' => $request->query('borrow_time'),
                'return_date' => $request->query('return_date'),
                'return_time' => $request->query('return_time'),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Rentang tanggal dan waktu tidak valid.',
            ], 422);
        }

        if ($end->lt($start)) {
            return response()->json([
                'message' => 'Waktu kembali harus setelah waktu mulai.',
            ], 422);
        }

        return response()->json(
            $availabilityService->getAvailabilityForUser(
                $item,
                $start,
                $end,
                $request->integer('exclude_item_borrowing_item_id') ?: null,
                (int) Auth::id(),
            ),
            200,
        );
    }

    public function downloadAttachment(ItemBorrowing $itemBorrowing)
    {
        $this->authorize('downloadAttachment', $itemBorrowing);

        if (! $itemBorrowing->attachment) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (! Storage::disk(PublicFileStorage::DISK)->exists($itemBorrowing->attachment)) {
            abort(404, 'File lampiran tidak tersedia.');
        }

        return response()->download(
            Storage::disk(PublicFileStorage::DISK)->path($itemBorrowing->attachment),
            basename($itemBorrowing->attachment)
        );
    }

    public function downloadSignedLetter(ItemBorrowing $itemBorrowing)
    {
        $this->authorize('downloadSignedLetter', $itemBorrowing);

        if (! $itemBorrowing->signed_letter) {
            abort(404, 'Surat yang ditandatangani tidak ditemukan.');
        }

        if (! Storage::disk(PublicFileStorage::DISK)->exists($itemBorrowing->signed_letter)) {
            abort(404, 'File surat tidak tersedia.');
        }

        return response()->download(
            Storage::disk(PublicFileStorage::DISK)->path($itemBorrowing->signed_letter),
            basename($itemBorrowing->signed_letter)
        );
    }

    public function cancel(
        ItemBorrowing $itemBorrowing,
        ItemBorrowingCancellationService $cancellationService,
    ) {
        $this->authorize('cancel', $itemBorrowing);

        $result = $cancellationService->cancel($itemBorrowing, Auth::user());

        if ($result !== CancellationResult::Cancelled) {
            $message = $result === CancellationResult::AlreadyCancelled
                ? 'Permintaan peminjaman barang sudah dibatalkan sebelumnya.'
                : 'Permintaan tidak dapat dibatalkan karena sudah diproses oleh admin.';

            return redirect()
                ->back()
                ->with('error', $message);
        }

        return redirect()
            ->back()
            ->with('success', 'Permintaan peminjaman barang berhasil dibatalkan.');
    }
}
