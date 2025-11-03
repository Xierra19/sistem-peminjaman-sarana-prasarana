<?php

namespace App\Http\Requests\Semester;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required','integer','between:2000,2100'],
            'term' => ['required', Rule::in(['ganjil','genap']), Rule::unique('master_semesters', 'term')->where(fn ($query) => $query->where('year', $this->input('year')))],
            'is_active' => ['sometimes','boolean'],
            'anchor_date' => ['nullable','date'],
            'start_date' => ['nullable','date'],
            'end_date' => ['nullable','date','after_or_equal:start_date'],
            'uts_start_date' => ['nullable','date'],
            'uts_end_date' => ['nullable','date','after_or_equal:uts_start_date'],
            'uas_start_date' => ['nullable','date'],
            'uas_end_date' => ['nullable','date','after_or_equal:uas_start_date'],
            'uts_week' => ['nullable','integer','min:1','max:30'],
            'uas_week' => ['nullable','integer','min:1','max:30'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->filled('start_date') && $this->filled('end_date') && $this->start_date > $this->end_date) {
                $validator->errors()->add('end_date', 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.');
            }

            if ($this->filled('uts_start_date') && $this->filled('uts_end_date') && $this->uts_start_date > $this->uts_end_date) {
                $validator->errors()->add('uts_end_date', 'Tanggal selesai UTS harus setelah atau sama dengan tanggal mulai UTS.');
            }

            if ($this->filled('uas_start_date') && $this->filled('uas_end_date') && $this->uas_start_date > $this->uas_end_date) {
                $validator->errors()->add('uas_end_date', 'Tanggal selesai UAS harus setelah atau sama dengan tanggal mulai UAS.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'year.required' => 'Tahun wajib diisi.',
            'year.integer' => 'Tahun harus berupa angka.',
            'year.between' => 'Tahun harus di antara 2000 hingga 2100.',
            'term.required' => 'Semester wajib dipilih.',
            'term.in' => 'Semester harus salah satu dari ganjil atau genap.',
            'term.unique' => 'Kombinasi tahun dan semester sudah terdaftar.',
            'end_date.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
            'uts_end_date.after_or_equal' => 'Tanggal selesai UTS harus setelah atau sama dengan tanggal mulai UTS.',
            'uas_end_date.after_or_equal' => 'Tanggal selesai UAS harus setelah atau sama dengan tanggal mulai UAS.',
        ];
    }
}
