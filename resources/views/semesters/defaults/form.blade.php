@php
    /** @var \App\Models\SemesterCourseDefault $default */
    $isEdit = $mode === 'edit';
    // Gunakan nama hari Indonesia agar sesuai dengan enum di database
    $days = [
        'Senin' => 'Senin',
        'Selasa' => 'Selasa',
        'Rabu' => 'Rabu',
        'Kamis' => 'Kamis',
        'Jumat' => 'Jumat',
        'Sabtu' => 'Sabtu',
        'Minggu' => 'Minggu',
    ];
@endphp

@extends('layouts.admin')

@section('title', ($isEdit ? 'Ubah' : 'Tambah').' Default Jadwal')
@section('page-title', ($isEdit ? 'Ubah' : 'Tambah').' Default Jadwal')

@section('content')
    <div class="max-w-4xl mx-auto bg-white shadow rounded p-6">
        <form action="{{ $isEdit ? route('admin.semesters.defaults.update', [$semester, $default]) : route('admin.semesters.defaults.store', $semester) }}" method="POST" class="space-y-6">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Mata Kuliah *</label>
                    <input type="text" name="course_name" value="{{ old('course_name', $default->course_name) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" maxlength="255" required>
                    @error('course_name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kode Mata Kuliah *</label>
                    <input type="text" name="course_code" value="{{ old('course_code', $default->course_code) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" maxlength="64" required>
                    @error('course_code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Hari *</label>
                    <select name="day_of_week" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">-- Pilih Hari --</option>
                        @foreach ($days as $value => $label)
                            <option value="{{ $value }}" @selected(old('day_of_week', $default->day_of_week) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('day_of_week')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-3">
                    <h3 class="text-lg font-semibold text-gray-800">Sesi Teori *</h3>
                    <p class="mt-1 text-xs text-gray-500">Gunakan format 24 jam (00:00 = tengah malam, 12:00 = tengah hari).</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
                    <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="theory_start_time" value="{{ old('theory_start_time', optional($default->theory_start_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                    @error('theory_start_time')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
                    <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="theory_end_time" value="{{ old('theory_end_time', optional($default->theory_end_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                    @error('theory_end_time')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ruang</label>
                    <select name="theory_room_id" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">-- Tanpa Ruang --</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected(old('theory_room_id', $default->theory_room_id) == $room->id)>{{ $room->name }}</option>
                        @endforeach
                    </select>
                    @error('theory_room_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-3 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Praktikum 1 (Opsional)</h3>
                        <span class="text-xs text-gray-500">Biarkan kosong bila tidak ada.</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
                        <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="practicum1_start_time" value="{{ old('practicum1_start_time', optional($default->practicum1_start_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        @error('practicum1_start_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
                        <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="practicum1_end_time" value="{{ old('practicum1_end_time', optional($default->practicum1_end_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        @error('practicum1_end_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ruang</label>
                        <select name="practicum1_room_id" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">-- Tanpa Ruang --</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected(old('practicum1_room_id', $default->practicum1_room_id) == $room->id)>{{ $room->name }}</option>
                            @endforeach
                        </select>
                        @error('practicum1_room_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-3 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Praktikum 2 (Opsional)</h3>
                        <span class="text-xs text-gray-500">Biarkan kosong bila tidak ada.</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
                        <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="practicum2_start_time" value="{{ old('practicum2_start_time', optional($default->practicum2_start_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        @error('practicum2_start_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
                        <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="practicum2_end_time" value="{{ old('practicum2_end_time', optional($default->practicum2_end_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        @error('practicum2_end_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ruang</label>
                        <select name="practicum2_room_id" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">-- Tanpa Ruang --</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected(old('practicum2_room_id', $default->practicum2_room_id) == $room->id)>{{ $room->name }}</option>
                            @endforeach
                        </select>
                        @error('practicum2_room_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">UTS (Opsional)</h3>
                <span class="text-xs text-gray-500">Isi bila jadwal UTS sudah tersedia.</span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal UTS</label>
                <input type="date" name="uts_exam_date" value="{{ old('uts_exam_date', optional($default->uts_exam_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                @error('uts_exam_date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
                <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="uts_start_time" value="{{ old('uts_start_time', optional($default->uts_start_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                @error('uts_start_time')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
                <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="uts_end_time" value="{{ old('uts_end_time', optional($default->uts_end_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                @error('uts_end_time')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Ruang</label>
                <select name="uts_room_id" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Tanpa Ruang --</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected(old('uts_room_id', $default->uts_room_id) == $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
                @error('uts_room_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">UAS (Opsional)</h3>
                <span class="text-xs text-gray-500">Isi bila jadwal UAS sudah tersedia.</span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal UAS</label>
                <input type="date" name="uas_exam_date" value="{{ old('uas_exam_date', optional($default->uas_exam_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                @error('uas_exam_date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
                <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="uas_start_time" value="{{ old('uas_start_time', optional($default->uas_start_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                @error('uas_start_time')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Jam Selesai</label>
                <input type="text" inputmode="numeric" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" maxlength="5" placeholder="HH:MM" name="uas_end_time" value="{{ old('uas_end_time', optional($default->uas_end_time)->format('H:i')) }}" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                @error('uas_end_time')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Ruang</label>
                <select name="uas_room_id" class="mt-1 w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Tanpa Ruang --</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected(old('uas_room_id', $default->uas_room_id) == $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
                @error('uas_room_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.semesters.defaults.index', $semester) }}" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Jadwal' }}
                </button>
            </div>
        </form>
    </div>
@endsection
