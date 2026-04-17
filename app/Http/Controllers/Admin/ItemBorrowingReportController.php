<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ItemBorrowingReportExport;
use App\Http\Controllers\Controller;
use App\Models\ItemBorrowing;
use App\Support\ItemBorrowingReportFilters;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ItemBorrowingReportController extends Controller
{
    private const STATUS_OPTIONS = ['waiting', 'approved', 'rejected', 'cancelled', 'returned'];

    public function index(Request $request)
    {
        $this->ensureItemAdmin($request);

        $filters = $this->validatedFilters($request);

        $query = ItemBorrowing::query()->with([
            'user:id,name,email,phone',
            'item:id,code,name,category,quantity',
        ]);

        ItemBorrowingReportFilters::apply($query, $filters);

        $itemBorrowings = $query->orderByDesc('created_at')->get()->values();

        $statusSummary = [
            'total' => $itemBorrowings->count(),
            'approved' => $itemBorrowings->where('status', 'approved')->count(),
            'waiting' => $itemBorrowings->whereIn('status', ['waiting', 'requested'])->count(),
            'rejected' => $itemBorrowings->where('status', 'rejected')->count(),
            'cancelled' => $itemBorrowings->where('status', 'cancelled')->count(),
            'returned' => $itemBorrowings->where('status', 'returned')->count(),
        ];

        return Inertia::render('Admin/ItemBorrowingReports/Index', [
            'itemBorrowings' => $itemBorrowings,
            'filters' => $filters,
            'statusSummary' => $statusSummary,
            'statusOptions' => self::STATUS_OPTIONS,
        ]);
    }

    public function export(Request $request)
    {
        $this->ensureItemAdmin($request);

        $filters = $this->validatedFilters($request);
        $fileName = 'item-borrowing-report-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ItemBorrowingReportExport($filters), $fileName);
    }

    private function ensureItemAdmin(Request $request): void
    {
        if (! $request->user()?->canManageItemModule()) {
            abort(403);
        }
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUS_OPTIONS)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $filters = array_merge([
            'search' => null,
            'status' => null,
            'start_date' => null,
            'end_date' => null,
        ], $validated);

        foreach ($filters as $key => $value) {
            if ($value === '') {
                $filters[$key] = null;
            }
        }

        if ($filters['start_date'] && $filters['end_date'] && $filters['start_date'] > $filters['end_date']) {
            [$filters['start_date'], $filters['end_date']] = [$filters['end_date'], $filters['start_date']];
        }

        return $filters;
    }
}
