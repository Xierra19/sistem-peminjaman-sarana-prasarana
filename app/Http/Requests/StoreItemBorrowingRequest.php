<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $minimumBorrowDate = now()->addDays(3)->toDateString();

        return [
            'item_id' => ['required', 'exists:items,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'borrow_date' => ['required', 'date', 'after_or_equal:'.$minimumBorrowDate],
            'return_date' => ['required', 'date', 'after_or_equal:borrow_date'],
            'quantity' => ['required', 'integer', 'min:1'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        $minimumBorrowDate = now()->addDays(3)->format('d/m/Y');

        return [
            'borrow_date.after_or_equal' => 'Tanggal peminjaman minimal '.$minimumBorrowDate.' (H+3 dari hari ini).',
            'return_date.after_or_equal' => 'Tanggal kembali harus sama atau setelah tanggal pinjam.',
        ];
    }
}
