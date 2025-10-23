@extends('layouts.admin')

@section('title', 'Preview Import Default Jadwal')
@section('page-title', 'Preview Import Default Jadwal')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800">Preview Data Import</h2>
                <p class="text-sm text-gray-600">Periksa kembali hasil parsing CSV sebelum disimpan.</p>
            </div>
            <a href="{{ route('admin.semesters.defaults.import.form', $semester) }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Ganti File</a>
        </div>

        <div class="overflow-x-auto bg-white shadow rounded">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Baris</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Mata Kuliah</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Kode</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Hari</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Teori</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Praktikum 1</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Praktikum 2</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($preview->rows as $row)
                        <tr class="align-top">
                            <td class="px-3 py-3 text-sm text-gray-700">#{{ $row->raw->lineNumber }}</td>
                            <td class="px-3 py-3 text-sm text-gray-900">
                                <div>{{ $row->raw->courseName }}</div>
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-700">{{ $row->raw->courseCode }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700">{{ $row->normalizedData['day_of_week'] ?? $row->raw->dayOfWeek }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700">
                                @php
                                    $theory = $row->normalizedData;
                                @endphp
                                @if ($theory['theory_start_time'] && $theory['theory_end_time'])
                                    {{ $theory['theory_start_time'] }} - {{ $theory['theory_end_time'] }}<br>
                                    <span class="text-xs text-gray-500">{{ $row->raw->theoryRoomCode ?: 'Tanpa ruang' }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-700">
                                @if ($row->normalizedData['practicum1_start_time'] && $row->normalizedData['practicum1_end_time'])
                                    {{ $row->normalizedData['practicum1_start_time'] }} - {{ $row->normalizedData['practicum1_end_time'] }}<br>
                                    <span class="text-xs text-gray-500">{{ $row->raw->practicum1RoomCode ?: 'Tanpa ruang' }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-700">
                                @if ($row->normalizedData['practicum2_start_time'] && $row->normalizedData['practicum2_end_time'])
                                    {{ $row->normalizedData['practicum2_start_time'] }} - {{ $row->normalizedData['practicum2_end_time'] }}<br>
                                    <span class="text-xs text-gray-500">{{ $row->raw->practicum2RoomCode ?: 'Tanpa ruang' }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm">
                                @if ($row->hasErrors())
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">ERROR</span>
                                    <ul class="mt-2 space-y-1 text-xs text-red-600 list-disc list-inside">
                                        @foreach ($row->errors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">OK</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-500">Tidak ada data yang dapat dipreview.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center">
            <a href="{{ route('admin.semesters.defaults.index', $semester) }}" class="text-sm text-gray-600 hover:text-gray-800">&larr; Kembali ke daftar</a>
            @if (!$preview->hasErrors() && count($preview->rows) > 0)
                <form action="{{ route('admin.semesters.defaults.import.commit', $semester) }}" method="POST" onsubmit="return confirm('Simpan semua baris OK ke database?');">
                    @csrf
                    <input type="hidden" name="payload" value="{{ $payload }}">
                    <input type="hidden" name="has_errors" value="0">
                    <button type="submit" class="rounded bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Commit Import</button>
                </form>
            @else
                <form action="{{ route('admin.semesters.defaults.import.commit', $semester) }}" method="POST">
                    @csrf
                    <input type="hidden" name="payload" value="{{ $payload }}">
                    <input type="hidden" name="has_errors" value="1">
                    <button type="submit" class="rounded bg-gray-400 px-4 py-2 text-sm font-semibold text-white cursor-not-allowed" disabled>Perbaiki Data Terlebih Dahulu</button>
                </form>
            @endif
        </div>
    </div>
@endsection