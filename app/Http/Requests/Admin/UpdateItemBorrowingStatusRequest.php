<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateItemBorrowingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled', 'returned'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('notes', ['required', 'string', 'max:500'], function ($input) {
            return in_array($input->status, ['rejected', 'cancelled'], true);
        });
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Catatan wajib diisi untuk penolakan atau pembatalan.',
        ];
    }
}
