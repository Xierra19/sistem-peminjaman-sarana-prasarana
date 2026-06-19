<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMultipleItemBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function ($item): array {
                $item = is_array($item) ? $item : [];
                $dates = $item['dates'] ?? [];

                if (
                    $dates === []
                    && ! empty($item['borrow_date'])
                    && ($item['borrow_date'] ?? null) === ($item['return_date'] ?? null)
                ) {
                    $dates = [$item['borrow_date']];
                    $item['start_time'] = $item['start_time'] ?? $item['borrow_time'] ?? null;
                    $item['end_time'] = $item['end_time'] ?? $item['return_time'] ?? null;
                }

                $item['dates'] = collect($dates)
                    ->filter(fn ($date) => is_string($date) && $date !== '')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                return $item;
            })
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'attachment' => ['required_without:resubmitted_from_id', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'resubmitted_from_id' => ['nullable', 'integer', 'exists:item_borrowings,id'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.dates' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.dates.*' => ['required', 'date_format:Y-m-d'],
            'items.*.start_time' => ['required', 'date_format:H:i'],
            'items.*.end_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $expandedCount = 0;
            $seenSchedules = [];

            foreach ((array) $this->input('items', []) as $index => $item) {
                $dates = collect($item['dates'] ?? [])->unique()->values();
                $expandedCount += $dates->count();

                foreach ($dates as $dateIndex => $date) {
                    $signature = implode('|', [
                        $item['item_id'] ?? '',
                        $item['quantity'] ?? '',
                        $date,
                        $item['start_time'] ?? '',
                        $item['end_time'] ?? '',
                    ]);

                    if (isset($seenSchedules[$signature])) {
                        $validator->errors()->add(
                            "items.{$index}.dates.{$dateIndex}",
                            'Jadwal identik untuk barang dan jumlah yang sama tidak boleh ditambahkan dua kali.',
                        );
                    } else {
                        $seenSchedules[$signature] = true;
                    }

                    $this->validatePeriod(
                        $validator,
                        $index,
                        "{$date} ".($item['start_time'] ?? ''),
                        "{$date} ".($item['end_time'] ?? ''),
                        "items.{$index}.dates.{$dateIndex}",
                    );
                }
            }

            if ($expandedCount > 20) {
                $validator->errors()->add('items', 'Maksimal 20 jadwal peminjaman dalam satu pengajuan.');
            }
        });
    }

    protected function validatePeriod(
        Validator $validator,
        int $index,
        string $startValue,
        string $endValue,
        string $dateErrorKey,
    ): void {
        try {
            $timezone = config('app.business_timezone');
            $start = Carbon::createFromFormat('Y-m-d H:i', $startValue, $timezone);
            $end = Carbon::createFromFormat('Y-m-d H:i', $endValue, $timezone);
        } catch (\Throwable) {
            return;
        }

        if ($start->toDateString() < $this->minimumBorrowDate($timezone)->toDateString()) {
            $validator->errors()->add($dateErrorKey, 'Tanggal peminjaman minimal H-7 dari tanggal pengajuan.');
        }

        if (! $end->gt($start)) {
            $validator->errors()->add("items.{$index}.end_time", 'Waktu selesai harus setelah waktu mulai.');
        }

        if ($start->format('H:i') < '07:00' || $start->format('H:i') >= '21:00') {
            $validator->errors()->add("items.{$index}.start_time", 'Jam mulai harus antara pukul 07:00 dan 20:30.');
        }

        if ($end->format('H:i') < '07:00' || $end->format('H:i') > '21:00') {
            $validator->errors()->add("items.{$index}.end_time", 'Jam selesai harus antara pukul 07:00 dan 21:00.');
        }
    }

    protected function minimumBorrowDate(string $timezone): Carbon
    {
        return Carbon::now($timezone)->addDays(7)->startOfDay();
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Masukkan judul kegiatan.',
            'title.string' => 'Judul kegiatan harus berupa teks.',
            'title.max' => 'Judul kegiatan maksimal 255 karakter.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'attachment.required_without' => 'Lampiran wajib diunggah untuk pengajuan baru.',
            'attachment.file' => 'Lampiran harus berupa file.',
            'attachment.mimes' => 'Lampiran harus berformat PDF, JPG, atau PNG.',
            'attachment.max' => 'Ukuran lampiran maksimal 2 MB.',
            'items.required' => 'Tambahkan minimal satu barang.',
            'items.min' => 'Tambahkan minimal satu barang.',
            'items.max' => 'Maksimal 20 kartu barang dalam satu pengajuan.',
            'items.*.item_id.required' => 'Pilih barang.',
            'items.*.item_id.exists' => 'Barang yang dipilih tidak valid.',
            'items.*.quantity.required' => 'Masukkan jumlah barang.',
            'items.*.quantity.min' => 'Jumlah barang minimal 1.',
            'items.*.dates.required' => 'Pilih minimal satu tanggal peminjaman.',
            'items.*.dates.array' => 'Daftar tanggal peminjaman tidak valid.',
            'items.*.dates.min' => 'Pilih minimal satu tanggal peminjaman.',
            'items.*.dates.max' => 'Maksimal 20 tanggal dalam satu kartu barang.',
            'items.*.dates.*.required' => 'Pilih tanggal peminjaman.',
            'items.*.dates.*.date_format' => 'Tanggal peminjaman tidak valid.',
            'items.*.start_time.required' => 'Pilih jam mulai.',
            'items.*.start_time.date_format' => 'Format jam mulai tidak valid.',
            'items.*.end_time.required' => 'Pilih jam selesai.',
            'items.*.end_time.date_format' => 'Format jam selesai tidak valid.',
            'resubmitted_from_id.integer' => 'Sumber pengajuan ulang tidak valid.',
            'resubmitted_from_id.exists' => 'Pengajuan sumber tidak ditemukan.',
        ];
    }
}
