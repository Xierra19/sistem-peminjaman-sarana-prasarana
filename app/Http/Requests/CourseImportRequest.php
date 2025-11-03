<?php

// file: app/Http/Requests/CourseImportRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourseImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ];
    }
}
