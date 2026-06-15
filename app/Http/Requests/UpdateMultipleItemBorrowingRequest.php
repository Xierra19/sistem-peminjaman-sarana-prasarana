<?php

namespace App\Http\Requests;

use App\Models\ItemBorrowing;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMultipleItemBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $itemBorrowing = $this->route('itemBorrowing');

        return $itemBorrowing instanceof ItemBorrowing
            && $this->user()?->can('update', $itemBorrowing) === true;
    }

    public function rules(): array
    {
        $itemBorrowing = $this->route('itemBorrowing');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'], // Optional for update
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'nullable',
                Rule::exists('item_borrowing_items', 'id')
                    ->where('item_borrowing_id', $itemBorrowing?->id),
            ],
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

                /** @var ItemBorrowing|null $itemBorrowing */
                $itemBorrowing = $this->route('itemBorrowing');
                $originalCutoff = $itemBorrowing?->created_at
                    ? $itemBorrowing->created_at->copy()->setTimezone($timezone)->startOfDay()->addDays(7)
                    : Carbon::now($timezone)->addDays(7)->startOfDay();
                $minimumDate = $originalCutoff->max(Carbon::now($timezone)->startOfDay());

                if ($start->toDateString() < $minimumDate->toDateString()) {
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
            'items.required' => 'Minimal 1 barang harus dipilih.',
            'items.min' => 'Minimal 1 barang harus dipilih.',
            'items.*.id.exists' => 'Detail barang tidak valid untuk pengajuan ini.',
            'items.*.borrow_time.required' => 'Jam mulai wajib diisi.',
            'items.*.return_time.required' => 'Jam kembali wajib diisi.',
        ];
    }
}
