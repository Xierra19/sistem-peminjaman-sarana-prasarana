<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Peminjaman Barang</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            font-size: 11px;
            margin: 0;
            padding: 24px;
            line-height: 1.5;
        }

        h1 {
            font-size: 20px;
            margin-bottom: 4px;
            color: #111827;
        }

        .subtitle {
            font-size: 12px;
            color: #4b5563;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f3f4f6;
            font-weight: 600;
            color: #1f2937;
        }

        tr:nth-child(even) {
            background-color: #f9fafb;
        }
    </style>
</head>
<body>
@php
    use Carbon\Carbon;
    $statusLabels = [
        'waiting' => 'Menunggu',
        'requested' => 'Menunggu',
        'needs_revision' => 'Perlu Direvisi',
        'approved' => 'Disetujui',
        'completed' => 'Selesai',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'returned' => 'Dikembalikan',
    ];
@endphp
    <h1>Laporan Peminjaman Barang</h1>
    <div class="subtitle">Dicetak pada {{ Carbon::parse($generatedAt)->locale('id')->translatedFormat('d F Y H:i') }} WIB</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal Pengajuan</th>
                <th>Pemohon</th>
                <th>Keperluan</th>
                <th>Barang</th>
                <th>Kode</th>
                <th>Kategori</th>
                <th>Qty</th>
                <th>Waktu Pinjam</th>
                <th>Waktu Kembali</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($itemBorrowings as $borrowing)
            @php
                $hasItems = $borrowing->items && $borrowing->items->isNotEmpty();
                if ($hasItems) {
                    $itemNames = $borrowing->items->map(fn ($p) => $p->item?->name)->filter()->implode(', ');
                    $itemCodes = $borrowing->items->map(fn ($p) => $p->item?->code)->filter()->implode(', ');
                    $itemCategories = $borrowing->items->map(fn ($p) => $p->item?->category)->filter()->implode(', ');
                    $totalQuantity = $borrowing->items->sum(fn ($p) => $p->quantity ?? 0);
                    $borrowDates = $borrowing->items->pluck('borrow_date')->filter()->sort()->values();
                    $returnDates = $borrowing->items->pluck('return_date')->filter()->sort()->values();
                    $borrowDate = $borrowDates->first();
                    $returnDate = $returnDates->last();
                } else {
                    $itemNames = $borrowing->singleItem?->name;
                    $itemCodes = $borrowing->singleItem?->code;
                    $itemCategories = $borrowing->singleItem?->category;
                    $totalQuantity = $borrowing->quantity ?? 0;
                    $borrowDate = $borrowing->borrow_date;
                    $returnDate = $borrowing->return_date;
                }
                $status = $borrowing->effective_status;
            @endphp
            <tr>
                <td>{{ $borrowing->id }}</td>
                <td>{{ $borrowing->created_at ? Carbon::parse($borrowing->created_at)->locale('id')->translatedFormat('d F Y H:i') : '-' }}</td>
                <td>{{ optional($borrowing->user)->name }}</td>
                <td>{{ $borrowing->title }}</td>
                <td>{{ $itemNames ?: '-' }}</td>
                <td>{{ $itemCodes ?: '-' }}</td>
                <td>{{ $itemCategories ?: '-' }}</td>
                <td>{{ $totalQuantity }}</td>
                <td>{{ $borrowDate ? Carbon::parse($borrowDate)->timezone(config('app.business_timezone'))->locale('id')->translatedFormat('d F Y H:i') . ' WIB' : '-' }}</td>
                <td>{{ $returnDate ? Carbon::parse($returnDate)->timezone(config('app.business_timezone'))->locale('id')->translatedFormat('d F Y H:i') . ' WIB' : '-' }}</td>
                <td>{{ $statusLabels[$status] ?? ucfirst($status ?? '-') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11" style="text-align: center; color: #6b7280;">Tidak ada data peminjaman barang.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>

