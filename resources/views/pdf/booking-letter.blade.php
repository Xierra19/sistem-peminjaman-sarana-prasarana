<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Peminjaman Ruangan</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 0;
            padding: 32px;
            line-height: 1.6;
        }

        .logo-wrapper {
            text-align: center;
            margin-bottom: 16px;
        }

        .logo-wrapper img {
            height: 80px;
        }

        h1 {
            font-size: 22px;
            text-align: center;
            margin-bottom: 16px;
            text-transform: uppercase;
        }

        .section-title {
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        td {
            padding: 6px 0;
            vertical-align: top;
        }

        td.label {
            width: 140px;
            font-weight: 600;
            color: #111827;
        }

        .signature {
            margin-top: 48px;
            text-align: right;
        }

        .signature p {
            margin: 0;
        }
    </style>
</head>
<body>
@php
    use Carbon\Carbon;
    $start = Carbon::parse($booking->start_time)->locale('id');
    $end = Carbon::parse($booking->end_time)->locale('id');
    $approvalDate = $approvedAt
        ? $approvedAt->copy()->locale('id')
        : Carbon::parse($generatedAt)->locale('id');
@endphp
    <div class="logo-wrapper">
        <img src="{{ public_path('images/LOGO_UEU_BY_ASU-06.png') }}" alt="Logo Universitas Esa Unggul">
    </div>
    <h1>Surat Peminjaman Ruangan</h1>
    @if ($booking->letter_number)
        <p style="text-align: left; font-weight: 600; margin-top: -12px; margin-bottom: 24px; font-size: 14px;">
            Nomor: {{ $booking->letter_number }}
        </p>
    @endif

    <p>
        Dengan ini kami menyatakan bahwa permohonan peminjaman ruangan berikut telah
        <strong>DISETUJUI</strong> oleh pengelola fasilitas kampus.
    </p>

    <div class="section-title">Informasi Permohonan</div>
    <table>
        <tr>
            <td class="label">Nomor Permohonan</td>
            <td>: {{ $booking->id }}</td>
        </tr>
        <tr>
            <td class="label">Pemohon</td>
            <td>: {{ optional($booking->user)->name }} ({{ optional($booking->user)->email }})</td>
        </tr>
        <tr>
            <td class="label">Judul Kegiatan</td>
            <td>: {{ $booking->title }}</td>
        </tr>
        <tr>
            <td class="label">Deskripsi</td>
            <td>: {{ $booking->description ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Detail Ruangan</div>
    <table>
        <tr>
            <td class="label">Ruangan</td>
            <td>: {{ optional($booking->room)->name }}</td>
        </tr>
        <tr>
            <td class="label">Gedung</td>
            <td>: {{ optional(optional($booking->room)->building)->name }}</td>
        </tr>
        <tr>
            <td class="label">Kampus</td>
            <td>: {{ optional(optional(optional($booking->room)->building)->campus)->name }}</td>
        </tr>
        <tr>
            <td class="label">Waktu Mulai</td>
            <td>: {{ $start->translatedFormat('d F Y H:i') }} WIB</td>
        </tr>
        <tr>
            <td class="label">Waktu Selesai</td>
            <td>: {{ $end->translatedFormat('d F Y H:i') }} WIB</td>
        </tr>
    </table>

    <p>
        Mohon untuk menjaga ketertiban dan kebersihan ruangan selama kegiatan berlangsung.
        Segala bentuk kerusakan menjadi tanggung jawab peminjam sesuai ketentuan yang berlaku.
    </p>

    <div class="signature">
        <p>Disetujui pada {{ $approvalDate->translatedFormat('d F Y H:i') }} WIB</p>
        <p>Biro Administrasi Pembelajaran Kampus Bekasi</p>
        <br><br>
        <br><br>        
        <p><strong>Biro Administrasi Pembelajaran Kampus Bekasi</strong></p>
    </div>
</body>
</html>
