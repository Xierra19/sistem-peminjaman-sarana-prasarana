@php
    /** @var \App\Models\MasterSemester $semester */
    $isEdit = $mode === 'edit';
@endphp

@extends('layouts.admin')

@section('title', $isEdit ? 'Ubah Semester' : 'Tambah Semester')
@section('page-title', $isEdit ? 'Ubah Semester' : 'Tambah Semester')

@section('content')
    <div class="max-w-3xl mx-auto bg-white shadow rounded p-6">
        <form action="{{ $isEdit ? route('admin.semesters.update', $semester) : route('admin.semesters.store') }}" method="POST" class="space-y-6">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tahun *</label>
                    <input type="number" name="year" value="{{ old('year', $semester->year) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" min="2000" max="2100" required>
                    @error('year')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Semester *</label>
                    <select name="term" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">-- Pilih --</option>
                        <option value="ganjil" @selected(old('term', $semester->term) === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected(old('term', $semester->term) === 'genap')>Genap</option>
                    </select>
                    @error('term')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tanggal Anchor</label>
                    <input type="date" name="anchor_date" value="{{ old('anchor_date', optional($semester->anchor_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    @error('anchor_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" id="is_active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked(old('is_active', $semester->is_active))>
                    <label for="is_active" class="text-sm font-medium text-gray-700">Jadikan semester aktif</label>
                    @error('is_active')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="{{ old('start_date', optional($semester->start_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    @error('start_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tanggal Selesai</label>
                    <input type="date" name="end_date" value="{{ old('end_date', optional($semester->end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    @error('end_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Minggu UTS</label>
                        <input type="number" name="uts_week" value="{{ old('uts_week', $semester->uts_week) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" min="1" max="30">
                        @error('uts_week')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Minggu UAS</label>
                        <input type="number" name="uas_week" value="{{ old('uas_week', $semester->uas_week) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" min="1" max="30">
                        @error('uas_week')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.semesters.index') }}" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Semester' }}
                </button>
            </div>
        </form>
    </div>
@endsection