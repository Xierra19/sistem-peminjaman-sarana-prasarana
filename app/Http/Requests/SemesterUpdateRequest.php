<?php

// file: app/Http/Requests/SemesterUpdateRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SemesterUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'uts_start_date' => ['nullable', 'date'],
            'uts_end_date' => ['nullable', 'date', 'after_or_equal:uts_start_date'],
            'uas_start_date' => ['nullable', 'date'],
            'uas_end_date' => ['nullable', 'date', 'after_or_equal:uas_start_date'],
            'teaching_weeks_before_uts' => ['required', 'integer', 'min:1', 'max:14'],
            'teaching_weeks_after_uts' => ['required', 'integer', 'min:1', 'max:14'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'uts_end_date.after_or_equal' => 'UTS end date must be on or after the UTS start date.',
            'uas_end_date.after_or_equal' => 'UAS end date must be on or after the UAS start date.',
        ];
    }
}

