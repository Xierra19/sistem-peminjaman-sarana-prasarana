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
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
            'resubmitted_from_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ];
    }

    public function messages(): array
    {
        $minimumStartDate = $this->minimumStartDate()->format('d/m/Y');

        return [
            'schedules.required' => 'Tambahkan minimal satu jadwal ruangan.',
            'schedules.*.dates.required' => 'Pilih minimal satu tanggal penggunaan.',
            'schedules.*.dates.min' => 'Pilih minimal satu tanggal penggunaan.',
            'schedules.*.dates.*.after_or_equal' => 'Tanggal penggunaan minimal '.$minimumStartDate.' (H+3 dari hari ini).',
        ];
    }

    protected function minimumStartDate(): Carbon
    {
        return Carbon::now(config('app.business_timezone'))->addDays(3)->startOfDay();
    }
}
