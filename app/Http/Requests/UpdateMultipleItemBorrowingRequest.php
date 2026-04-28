<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMultipleItemBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $minimumBorrowDate = now()->addDays(3)->toDateString();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'], // Optional for update
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'exists:item_borrowing_items,id'], // Existing item
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.borrow_date' => ['required', 'date', 'after_or_equal:'.$minimumBorrowDate],
            'items.*.return_date' => ['required', 'date', 'after_or_equal:items.*.borrow_date'],
        ];
    }

    public function messages(): array
    {
        $minimumBorrowDate = now()->addDays(3)->format('d/m/Y');

        return [
            'items.required' => 'Minimal 1 barang harus dipilih.',
            'items.min' => 'Minimal 1 barang harus dipilih.',
            'items.*.borrow_date.after_or_equal' => 'Tanggal peminjaman minimal '.$minimumBorrowDate.' (H+3 dari hari ini).',
            'items.*.return_date.after_or_equal' => 'Tanggal kembali harus sama atau setelah tanggal pinjam.',
        ];
    }
}

