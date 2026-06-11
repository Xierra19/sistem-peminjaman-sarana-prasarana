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
            'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'signed_letter' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('notes', ['required', 'string', 'max:500'], function ($input) {
            return in_array($input->status, ['rejected', 'cancelled'], true);
        });

        $validator->sometimes('signed_letter', ['required'], function ($input) {
            return $input->status === 'approved';
        });

        $validator->sometimes('signed_letter', ['prohibited'], function ($input) {
            return $input->status === 'cancelled';
        });
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Catatan wajib diisi untuk penolakan atau pembatalan.',
            'signed_letter.required' => 'Surat yang sudah ditandatangani wajib diunggah saat menyetujui peminjaman.',
            'signed_letter.prohibited' => 'Upload surat hanya tersedia saat menyetujui atau menolak peminjaman.',
            'signed_letter.mimes' => 'File surat harus berformat PDF, JPG, JPEG, atau PNG.',
            'signed_letter.max' => 'Ukuran file surat maksimal 2MB.',
        ];
    }
}
