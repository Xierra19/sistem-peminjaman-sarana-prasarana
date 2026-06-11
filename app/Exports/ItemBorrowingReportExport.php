<?php

namespace App\Exports;

use App\Models\ItemBorrowing;
use App\Support\ItemBorrowingReportFilters;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ItemBorrowingReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = ItemBorrowing::query()
            ->with([
                'user:id,name,email,phone',
                'items.item:id,code,name,category',
                'singleItem:id,code,name,category',
            ]);

        ItemBorrowingReportFilters::apply($query, $this->filters);

        return $query->orderByDesc('created_at')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal Pengajuan',
            'Status',
            'Nama Pemohon',
            'Email Pemohon',
            'No. Telepon',
            'Keperluan',
            'Deskripsi',
            'Waktu Pinjam',
            'Waktu Kembali',
            'Jumlah',
            'Kode Barang',
            'Nama Barang',
            'Kategori',
        ];
    }

    public function map($itemBorrowing): array
    {
        $hasItems = $itemBorrowing->items && $itemBorrowing->items->isNotEmpty();

        if ($hasItems) {
            $itemNames = $itemBorrowing->items->map(fn ($p) => $p->item?->name)->filter()->implode(', ');
            $itemCodes = $itemBorrowing->items->map(fn ($p) => $p->item?->code)->filter()->implode(', ');
            $itemCategories = $itemBorrowing->items->map(fn ($p) => $p->item?->category)->filter()->implode(', ');
            $totalQuantity = $itemBorrowing->items->sum(fn ($p) => $p->quantity ?? 0);
            $borrowDates = $itemBorrowing->items->pluck('borrow_date')->filter()->sort()->values();
            $returnDates = $itemBorrowing->items->pluck('return_date')->filter()->sort()->values();
            $borrowDate = $borrowDates->first();
            $returnDate = $returnDates->last();
        } else {
            $itemNames = $itemBorrowing->singleItem?->name;
            $itemCodes = $itemBorrowing->singleItem?->code;
            $itemCategories = $itemBorrowing->singleItem?->category;
            $totalQuantity = $itemBorrowing->quantity ?? 0;
            $borrowDate = $itemBorrowing->borrow_date;
            $returnDate = $itemBorrowing->return_date;
        }

        return [
            $itemBorrowing->id,
            $this->formatDateTime($itemBorrowing->created_at),
            strtoupper((string) $itemBorrowing->effective_status),
            $itemBorrowing->user?->name,
            $itemBorrowing->user?->email,
            $itemBorrowing->user?->phone,
            $itemBorrowing->title,
            $itemBorrowing->description,
            $this->formatDateTime($borrowDate),
            $this->formatDateTime($returnDate),
            $totalQuantity,
            $itemCodes,
            $itemNames,
            $itemCategories,
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->timezone(config('app.business_timezone'))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }
    }

}
