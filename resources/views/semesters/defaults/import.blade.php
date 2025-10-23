@extends('layouts.admin')

@section('title', 'Import Default Jadwal')
@section('page-title', 'Import Default Jadwal')

@section('content')
    <div class="max-w-3xl mx-auto bg-white shadow rounded p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Import CSV Default Jadwal</h2>
        <p class="text-sm text-gray-600 mb-6">Unggah file CSV dengan header: <code class="bg-gray-100 px-2 py-1 rounded text-xs">course_name,course_code,theory_time,practicum1_time,practicum2_time,day_of_week,theory_room_code,practicum1_room_code,practicum2_room_code</code>.</p>

        <form action="{{ route('admin.semesters.defaults.import.preview', $semester) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700">File CSV *</label>
                <input type="file" name="csv_file" accept=".csv,text/csv" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                @error('csv_file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.semesters.defaults.index', $semester) }}" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Preview</button>
            </div>
        </form>
    </div>
@endsection