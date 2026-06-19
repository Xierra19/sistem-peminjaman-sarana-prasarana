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

        return Inertia::render('ItemBorrowings/Create', $this->itemFormProps($itemBorrowing, 'resubmission'));
    }

    private function itemFormProps(?ItemBorrowing $source = null, string $formMode = 'create'): array
    {
        $items = Item::query()
            ->select('id', 'code', 'name', 'category', 'quantity', 'is_available')
            ->orderBy('name')
            ->get();

        return [
            'items' => $items,
            'minimumBorrowDate' => Carbon::now(config('app.business_timezone'))->addDays(7)->toDateString(),
            'formMode' => $formMode,
            'itemBorrowingId' => $formMode === 'revision' ? $source?->id : null,
            'sourceItemBorrowingId' => $formMode === 'resubmission' ? $source?->id : null,
            'initialData' => $source ? $this->itemBorrowingInitialData($source) : null,
            'revisionNote' => $source?->logs
                ->where('action', $formMode === 'revision'
                    ? ItemBorrowing::STATUS_NEEDS_REVISION
                    : ItemBorrowing::STATUS_REJECTED)
                ->last()
                ?->description,
            'existingAttachment' => $source?->attachment,
        ];
    }

    private function itemBorrowingInitialData(ItemBorrowing $itemBorrowing): array
    {
        $timezone = config('app.business_timezone');
        $rows = $itemBorrowing->items->map(function ($pivot) use ($timezone): array {
            $start = $pivot->borrow_date->timezone($timezone);
            $end = $pivot->return_date->timezone($timezone);

            return [
                'id' => $pivot->id,
                'item_id' => $pivot->item_id,
                'quantity' => $pivot->quantity,
                'borrow_date' => $start->format('Y-m-d'),
                'borrow_time' => $start->format('H:i'),
                'return_date' => $end->format('Y-m-d'),
                'return_time' => $end->format('H:i'),
            ];
        });

        if ($itemBorrowing->singleItem && $rows->isEmpty()) {
            $rows->push([
                'item_id' => $itemBorrowing->singleItem->id,
                'quantity' => $itemBorrowing->quantity,
                'borrow_date' => $itemBorrowing->borrow_date->timezone($timezone)->format('Y-m-d'),
                'borrow_time' => $itemBorrowing->borrow_date->timezone($timezone)->format('H:i'),
                'return_date' => $itemBorrowing->return_date->timezone($timezone)->format('Y-m-d'),
                'return_time' => $itemBorrowing->return_date->timezone($timezone)->format('H:i'),
            ]);
        }

        $sameDayCards = $rows
            ->filter(fn (array $row) => $row['borrow_date'] === $row['return_date'])
            ->groupBy(fn (array $row) => implode('|', [
                $row['item_id'],
                $row['quantity'],
                $row['borrow_time'],
                $row['return_time'],
            ]))
            ->map(function ($group): array {
                $first = $group->first();

                return [
                    'item_id' => $first['item_id'],
                    'quantity' => $first['quantity'],
                    'dates' => $group->pluck('borrow_date')->unique()->sort()->values()->all(),
                    'start_time' => $first['borrow_time'],
                    'end_time' => $first['return_time'],
                ];
            });

        $legacyRangeCards = $rows
            ->reject(fn (array $row) => $row['borrow_date'] === $row['return_date'])
            ->map(fn (array $row): array => [
                'item_id' => $row['item_id'],
                'quantity' => $row['quantity'],
                'dates' => [$row['borrow_date']],
                'start_time' => $row['borrow_time'],
                'end_time' => $row['return_time'] > $row['borrow_time']
                    ? $row['return_time']
                    : '',
            ]);

        $formItems = $sameDayCards->concat($legacyRangeCards)->values();

        return [
            'title' => $itemBorrowing->title,
            'description' => $itemBorrowing->description,
            'items' => $formItems->values()->all(),
        ];
    }

    public function edit(ItemBorrowing $itemBorrowing)
    {
        $this->authorize('edit', $itemBorrowing);

        $props = $this->itemFormProps($itemBorrowing, 'revision');
        $props['minimumBorrowDate'] = $itemBorrowing->created_at
            ->copy()
            ->setTimezone(config('app.business_timezone'))
            ->startOfDay()
            ->addDays(7)
            ->max(Carbon::now(config('app.business_timezone'))->startOfDay())
            ->toDateString();

        return Inertia::render('ItemBorrowings/Create', $props);
    }

    public function store(
        StoreMultipleItemBorrowingRequest $request,
        ItemBorrowingWorkflow $workflow,
    ) {
        $validated = $request->validated();
        $workflow->create($validated, $request->file('attachment'), $request->user());

        return redirect()
            ->route('item-borrowings.index')
            ->with('success', 'Permintaan peminjaman '.count($validated['items']).' kartu barang berhasil dibuat dan menunggu persetujuan.');
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
            'dates' => 'nullable|array|max:20',
            'dates.*' => 'date_format:Y-m-d|distinct',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'borrow_date' => 'nullable|date_format:Y-m-d',
            'borrow_time' => 'nullable|date_format:H:i',
            'return_date' => 'nullable|date_format:Y-m-d',
            'return_time' => 'nullable|date_format:H:i',
            'exclude_item_borrowing_item_id' => 'nullable|integer',
            'exclude_item_borrowing_id' => 'nullable|integer',
        ]);

        $dates = collect($request->query('dates', []))
            ->filter(fn ($date) => is_string($date) && $date !== '')
            ->unique()
            ->sort()
            ->values();

        if ($dates->isNotEmpty()) {
            if (! $request->query('start_time') || ! $request->query('end_time')) {
                return response()->json(['message' => 'Jam mulai dan jam selesai wajib dipilih.'], 422);
            }

            if ($request->query('end_time') <= $request->query('start_time')) {
                return response()->json(['message' => 'Jam selesai harus setelah jam mulai.'], 422);
            }

            $dailyAvailability = $dates->map(function (string $date) use (
                $request,
                $item,
                $availabilityService,
                $period,
            ): array {
                [$start, $end] = $period->parse([
                    'borrow_date' => $date,
                    'borrow_time' => $request->query('start_time'),
                    'return_date' => $date,
                    'return_time' => $request->query('end_time'),
                ]);

                return [
                    'date' => $date,
                    ...$availabilityService->getAvailabilityForBorrowingUser(
                        $item,
                        $start,
                        $end,
                        $request->integer('exclude_item_borrowing_id') ?: null,
                        (int) Auth::id(),
                    ),
                ];
            })->all();

            return response()->json([
                'available' => collect($dailyAvailability)->every(fn (array $day) => $day['available']),
                'daily_availability' => $dailyAvailability,
            ]);
        }

        if (
            ! $request->query('borrow_date')
            || ! $request->query('borrow_time')
            || ! $request->query('return_date')
            || ! $request->query('return_time')
        ) {
            return response()->json(['message' => 'Rentang tanggal dan waktu wajib dilengkapi.'], 422);
        }

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

        if ($end->lte($start)) {
            return response()->json([
                'message' => 'Waktu kembali harus setelah waktu mulai.',
            ], 422);
        }

        $availability = $request->integer('exclude_item_borrowing_id')
            ? $availabilityService->getAvailabilityForBorrowingUser(
                $item,
                $start,
                $end,
                $request->integer('exclude_item_borrowing_id'),
                (int) Auth::id(),
            )
            : $availabilityService->getAvailabilityForUser(
                $item,
                $start,
                $end,
                $request->integer('exclude_item_borrowing_item_id') ?: null,
                (int) Auth::id(),
            );

        return response()->json($availability, 200);
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
