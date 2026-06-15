<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Booking Ruangan</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
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
        'waiting' => 'Menunggu Persetujuan',
        'needs_revision' => 'Perlu Direvisi',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa',
    ];
    $normalizeStatus = static fn (?string $status): ?string => in_array($status, ['pending', 'requested'], true) ? 'waiting' : $status;
@endphp
    <h1>Laporan Booking Ruangan</h1>
    <div class="subtitle">Dicetak pada {{ Carbon::parse($generatedAt)->locale('id')->translatedFormat('d F Y H:i') }} WIB</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Judul</th>
                <th>Pemohon</th>
                <th>Ruangan dan Jadwal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($bookings as $booking)
            <tr>
                <td>{{ $booking->id }}</td>
                <td>{{ $booking->title }}</td>
                <td>{{ optional($booking->user)->name }}</td>
                <td>
                    @foreach ($booking->roomSchedules as $schedule)
                        <div style="margin-bottom: 4px;">
                            <strong>{{ $schedule->room?->name ?? '-' }}</strong>
                            · {{ $schedule->room?->building?->name ?? '-' }}
                            · {{ $schedule->room?->building?->campus?->name ?? '-' }}
                            <br>
                            {{ $schedule->schedule_summary }}
                        </div>
                    @endforeach
                </td>
                @php($status = $normalizeStatus($booking->status))
                <td>{{ $statusLabels[$status] ?? ucfirst($status ?? '-') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #6b7280;">Tidak ada data booking.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
