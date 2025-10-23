@extends('layouts.admin')

@section('title', 'Default Jadwal - '.$semester->year.' '.$semester->term)
@section('page-title', 'Default Jadwal - '.$semester->year.' '.$semester->term)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800">Default Jadwal</h2>
            <p class="text-sm text-gray-600">Semester {{ $semester->term }} {{ $semester->year }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.semesters.defaults.import.form', $semester) }}" class="inline-flex items-center rounded border border-indigo-500 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">Import CSV</a>
            <a href="{{ route('admin.semesters.defaults.create', $semester) }}" class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">+ Tambah Jadwal</a>
        </div>
    </div>

    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nama Matkul</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Kode</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Hari</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Teori</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Praktikum 1</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Praktikum 2</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($defaults as $default)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-3 text-sm text-gray-900">{{ $default->course_name }}</td>
                        <td class="px-3 py-3 text-sm text-gray-700">{{ $default->course_code }}</td>
                        <td class="px-3 py-3 text-sm text-gray-700">{{ $default->day_of_week }}</td>
                        <td class="px-3 py-3 text-sm text-gray-700">
                            <div>{{ optional($default->theory_start_time)->format('H:i') }} - {{ optional($default->theory_end_time)->format('H:i') }}</div>
                            <div class="text-xs text-gray-500">{{ optional($default->theoryRoom)->name ?? 'Tanpa ruang' }}</div>
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-700">
                            @if ($default->practicum1_start_time && $default->practicum1_end_time)
                                <div>{{ optional($default->practicum1_start_time)->format('H:i') }} - {{ optional($default->practicum1_end_time)->format('H:i') }}</div>
                                <div class="text-xs text-gray-500">{{ optional($default->practicum1Room)->name ?? 'Tanpa ruang' }}</div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-700">
                            @if ($default->practicum2_start_time && $default->practicum2_end_time)
                                <div>{{ optional($default->practicum2_start_time)->format('H:i') }} - {{ optional($default->practicum2_end_time)->format('H:i') }}</div>
                                <div class="text-xs text-gray-500">{{ optional($default->practicum2Room)->name ?? 'Tanpa ruang' }}</div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-700">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.semesters.defaults.edit', [$semester, $default]) }}" class="text-amber-600 hover:text-amber-800">Edit</a>
                                <form action="{{ route('admin.semesters.defaults.destroy', [$semester, $default]) }}" method="POST" onsubmit="return confirm('Hapus jadwal ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">Belum ada default jadwal untuk semester ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection