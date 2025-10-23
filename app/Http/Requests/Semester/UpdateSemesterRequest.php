<?php

namespace App\Http\Requests\Semester;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $semesterId = $this->route('semester')?->id;

        return [
            'year' => ['required','integer','between:2000,2100'],
            'term' => ['required', Rule::in(['ganjil','genap']), Rule::unique('master_semesters', 'term')->ignore($semesterId)->where(fn ($query) => $query->where('year', $this->input('year')))],
            'is_active' => ['sometimes','boolean'],
            'anchor_date' => ['nullable','date'],
            'start_date' => ['nullable','date'],
            'end_date' => ['nullable','date','after_or_equal:start_date'],
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
        ];
    }
}