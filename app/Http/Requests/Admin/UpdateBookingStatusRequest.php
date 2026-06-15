<?php

namespace App\Http\Requests\Admin;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                Booking::STATUS_APPROVED,
                Booking::STATUS_REJECTED,
                Booking::STATUS_CANCELLED,
            ])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('notes', ['required', 'string', 'max:500'], function ($input) {
            return $input->status === Booking::STATUS_CANCELLED;
        });
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Catatan wajib diisi ketika membatalkan booking.',
        ];
    }
}
