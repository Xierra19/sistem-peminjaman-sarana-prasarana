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

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'attachment' => ['required_without:resubmitted_from_id', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'resubmitted_from_id' => ['nullable', 'integer', 'exists:item_borrowings,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.borrow_date' => ['required', 'date_format:Y-m-d'],
            'items.*.borrow_time' => ['required', 'date_format:H:i'],
            'items.*.return_date' => ['required', 'date_format:Y-m-d'],
            'items.*.return_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                try {
                    $timezone = config('app.business_timezone');
                    $start = Carbon::createFromFormat('Y-m-d H:i', ($item['borrow_date'] ?? '').' '.($item['borrow_time'] ?? ''), $timezone);
                    $end = Carbon::createFromFormat('Y-m-d H:i', ($item['return_date'] ?? '').' '.($item['return_time'] ?? ''), $timezone);
                } catch (\Throwable) {
                    continue;
                }

                if ($start->toDateString() < Carbon::now($timezone)->addDays(7)->toDateString()) {
                    $validator->errors()->add("items.{$index}.borrow_date", 'Tanggal peminjaman minimal H-7 dari tanggal pengajuan.');
                }

                if (! $end->gt($start)) {
                    $validator->errors()->add("items.{$index}.return_time", 'Waktu kembali harus setelah waktu mulai.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'attachment.required' => 'Lampiran wajib diunggah (PDF/JPG/PNG max 2MB).',
            'items.required' => 'Minimal 1 barang harus dipilih.',
            'items.min' => 'Minimal 1 barang harus dipilih.',
            'items.*.borrow_time.required' => 'Jam mulai wajib diisi.',
            'items.*.return_time.required' => 'Jam kembali wajib diisi.',
        ];
    }
}
