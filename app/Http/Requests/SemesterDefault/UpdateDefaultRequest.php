<?php

namespace App\Http\Requests\SemesterDefault;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDefaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_name' => ['required','string','max:255'],
            'course_code' => ['required','string','max:64'],
            // Sesuaikan dengan enum di DB (nama hari Indonesia)
            'day_of_week' => ['required','in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu'],
            'theory_start_time' => ['required','date_format:H:i'],
            'theory_end_time' => ['required','date_format:H:i','after:theory_start_time'],
            'theory_room_id' => ['nullable','exists:rooms,id'],
            'practicum1_start_time' => ['nullable','date_format:H:i'],
            'practicum1_end_time' => ['nullable','date_format:H:i','after:practicum1_start_time'],
            'practicum1_room_id' => ['nullable','exists:rooms,id'],
            'practicum2_start_time' => ['nullable','date_format:H:i'],
            'practicum2_end_time' => ['nullable','date_format:H:i','after:practicum2_start_time'],
            'practicum2_room_id' => ['nullable','exists:rooms,id'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'theory_start_time' => $this->normalizeTime($this->input('theory_start_time')),
            'theory_end_time' => $this->normalizeTime($this->input('theory_end_time')),
            'practicum1_start_time' => $this->normalizeTime($this->input('practicum1_start_time')),
            'practicum1_end_time' => $this->normalizeTime($this->input('practicum1_end_time')),
            'practicum2_start_time' => $this->normalizeTime($this->input('practicum2_start_time')),
            'practicum2_end_time' => $this->normalizeTime($this->input('practicum2_end_time')),
            'theory_room_id' => $this->input('theory_room_id') ?: null,
            'practicum1_room_id' => $this->input('practicum1_room_id') ?: null,
            'practicum2_room_id' => $this->input('practicum2_room_id') ?: null,
        ]);
    }

    private function normalizeTime(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = is_string($value) ? trim($value) : $value;

        if ($value === '' || $value === false) {
            return null;
        }

        if (is_string($value) && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            $parts = explode(':', $value);
            $hour = str_pad((string) ((int) $parts[0]), 2, '0', STR_PAD_LEFT);
            $minute = $parts[1] ?? '00';

            return $hour.':'.$minute;
        }

        if (is_string($value)) {
            $parsed = date_create($value);
            if ($parsed instanceof \DateTimeInterface) {
                return $parsed->format('H:i');
            }
        }

        return $value;
    }

    public function messages(): array
    {
        return [
            'course_name.required' => 'Nama mata kuliah wajib diisi.',
            'course_code.required' => 'Kode mata kuliah wajib diisi.',
            'day_of_week.required' => 'Hari wajib dipilih.',
            'day_of_week.in' => 'Hari tidak valid.',
            'theory_start_time.required' => 'Jam mulai teori wajib diisi.',
            'theory_end_time.after' => 'Jam selesai teori harus setelah jam mulai.',
            'practicum1_end_time.after' => 'Jam selesai praktikum 1 harus setelah jam mulai.',
            'practicum2_end_time.after' => 'Jam selesai praktikum 2 harus setelah jam mulai.',
            'theory_room_id.exists' => 'Ruang teori tidak ditemukan.',
            'practicum1_room_id.exists' => 'Ruang praktikum 1 tidak ditemukan.',
            'practicum2_room_id.exists' => 'Ruang praktikum 2 tidak ditemukan.',
        ];
    }
}
