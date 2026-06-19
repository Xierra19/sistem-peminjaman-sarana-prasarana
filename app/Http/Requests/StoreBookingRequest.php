<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $schedules = collect($this->input('schedules', []))
            ->map(function ($schedule): array {
                $schedule = is_array($schedule) ? $schedule : [];
                $dates = $schedule['dates'] ?? [];

                if (! is_array($dates)) {
                    $dates = [$dates];
                }

                if ($dates === [] && ! empty($schedule['date'])) {
                    $dates = [$schedule['date']];
                }

                $schedule['dates'] = collect($dates)
                    ->filter(fn ($date) => is_string($date) && $date !== '')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                unset($schedule['date']);

                return $schedule;
            })
            ->values()
            ->all();

        $this->merge(['schedules' => $schedules]);
    }

    public function rules(): array
    {
        $minimumStartDate = $this->minimumStartDate();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'schedules' => ['required', 'array', 'min:1', 'max:20'],
            'schedules.*.room_id' => ['required', 'exists:rooms,id'],
            'schedules.*.dates' => ['required', 'array', 'min:1', 'max:20'],
            'schedules.*.dates.*' => ['required', 'date', 'after_or_equal:'.$minimumStartDate->toDateString()],
            'schedules.*.start_time' => ['required', 'date_format:H:i', 'after_or_equal:07:00', 'before:21:00'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time', 'before_or_equal:21:00'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
            'resubmitted_from_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ];
    }

    public function messages(): array
    {
        $minimumStartDate = $this->minimumStartDate()->format('d/m/Y');

        return [
            'title.required' => 'Masukkan judul kegiatan.',
            'title.string' => 'Judul kegiatan harus berupa teks.',
            'title.max' => 'Judul kegiatan maksimal 255 karakter.',
            'description.string' => 'Deskripsi kegiatan harus berupa teks.',
            'schedules.required' => 'Tambahkan minimal satu jadwal ruangan.',
            'schedules.array' => 'Data jadwal ruangan tidak valid.',
            'schedules.min' => 'Tambahkan minimal satu jadwal ruangan.',
            'schedules.max' => 'Maksimal 20 jadwal ruangan dalam satu pengajuan.',
            'schedules.*.room_id.required' => 'Pilih ruangan.',
            'schedules.*.room_id.exists' => 'Ruangan yang dipilih tidak valid.',
            'schedules.*.dates.required' => 'Pilih minimal satu tanggal penggunaan.',
            'schedules.*.dates.array' => 'Tanggal penggunaan tidak valid.',
            'schedules.*.dates.min' => 'Pilih minimal satu tanggal penggunaan.',
            'schedules.*.dates.max' => 'Maksimal 20 tanggal dalam satu jadwal.',
            'schedules.*.dates.*.required' => 'Pilih tanggal penggunaan.',
            'schedules.*.dates.*.date' => 'Tanggal penggunaan tidak valid.',
            'schedules.*.dates.*.after_or_equal' => 'Tanggal penggunaan minimal '.$minimumStartDate.' (H+3 dari hari ini).',
            'schedules.*.start_time.required' => 'Pilih jam mulai.',
            'schedules.*.start_time.date_format' => 'Format jam mulai tidak valid.',
            'schedules.*.start_time.after_or_equal' => 'Jam mulai paling awal pukul 07:00.',
            'schedules.*.start_time.before' => 'Jam mulai paling akhir pukul 20:30.',
            'schedules.*.end_time.required' => 'Pilih jam selesai.',
            'schedules.*.end_time.date_format' => 'Format jam selesai tidak valid.',
            'schedules.*.end_time.after' => 'Jam selesai harus lebih akhir dari jam mulai.',
            'schedules.*.end_time.before_or_equal' => 'Jam selesai paling akhir pukul 21:00.',
            'attachment.file' => 'Lampiran harus berupa file.',
            'attachment.mimes' => 'Lampiran harus berformat PDF, JPG, atau PNG.',
            'attachment.max' => 'Ukuran lampiran maksimal 2 MB.',
            'resubmitted_from_id.integer' => 'Sumber pengajuan ulang tidak valid.',
            'resubmitted_from_id.exists' => 'Pengajuan sumber tidak ditemukan.',
        ];
    }

    protected function minimumStartDate(): Carbon
    {
        return Carbon::now(config('app.business_timezone'))->addDays(3)->startOfDay();
    }
}
