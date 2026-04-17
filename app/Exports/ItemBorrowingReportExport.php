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
                'item:id,code,name,category,quantity',
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
            'Tanggal Pinjam',
            'Tanggal Kembali',
            'Jumlah',
            'Kode Barang',
            'Nama Barang',
            'Kategori',
        ];
    }

    public function map($itemBorrowing): array
    {
        return [
            $itemBorrowing->id,
            $this->formatDateTime($itemBorrowing->created_at),
            strtoupper((string) $itemBorrowing->status),
            $itemBorrowing->user?->name,
            $itemBorrowing->user?->email,
            $itemBorrowing->user?->phone,
            $itemBorrowing->title,
            $itemBorrowing->description,
            $this->formatDate($itemBorrowing->borrow_date),
            $this->formatDate($itemBorrowing->return_date),
            $itemBorrowing->quantity,
            $itemBorrowing->item?->code,
            $itemBorrowing->item?->name,
            $itemBorrowing->item?->category,
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }
    }

    private function formatDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }
    }
}
