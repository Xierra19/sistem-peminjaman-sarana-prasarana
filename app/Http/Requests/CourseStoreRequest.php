<?php

// file: app/Http/Requests/CourseStoreRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourseStoreRequest extends FormRequest
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
            'semester_id' => ['required', 'exists:semesters,id'],
            'course_code' => ['required', 'string', 'max:50'],
            'course_name' => ['required', 'string', 'max:150'],
        ];
    }
}
