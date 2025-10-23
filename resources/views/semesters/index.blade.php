@extends('layouts.admin')

@section('title', 'Daftar Semester')
@section('page-title', 'Daftar Semester')

@section('content')
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800">Daftar Semester</h2>
            <p class="text-sm text-gray-600">Kelola data semester akademik.</p>
        </div>
        <a href="{{ route('admin.semesters.create') }}" class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            + Tambah Semester
        </a>
    </div>

    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tahun</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Semester</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Aktif</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Periode</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($semesters as $semester)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $semester->year }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 capitalize">{{ $semester->term }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold {{ $semester->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                <span class="h-2 w-2 rounded-full {{ $semester->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                {{ $semester->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            @if ($semester->start_date && $semester->end_date)
                                {{ $semester->start_date->format('d M Y') }} &ndash; {{ $semester->end_date->format('d M Y') }}
                            @else
                                <span class="text-gray-400">Belum diatur</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.semesters.defaults.index', $semester) }}" class="text-blue-600 hover:text-blue-800">Defaults</a>
                                <a href="{{ route('admin.semesters.edit', $semester) }}" class="text-amber-600 hover:text-amber-800">Edit</a>
                                <form action="{{ route('admin.semesters.toggle-active', $semester) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-indigo-600 hover:text-indigo-800">{{ $semester->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form action="{{ route('admin.semesters.destroy', $semester) }}" method="POST" class="inline" onsubmit="return confirm('Hapus semester ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Belum ada data semester.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection