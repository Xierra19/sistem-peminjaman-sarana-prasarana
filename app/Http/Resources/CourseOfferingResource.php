<?php

// file: app/Http/Resources/CourseOfferingResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CourseOffering
 */
class CourseOfferingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $exams = $this->courseExamSchedules ?? collect();
        $uts = $exams->firstWhere('exam_type', 'UTS');
        $uas = $exams->firstWhere('exam_type', 'UAS');

        return [
            'id' => $this->id,
            'course_code' => $this->course_code,
            'course_name' => $this->course_name,
            'class_group' => $this->class_group,
            'meetings_count' => (int) ($this->meetings_count ?? 0),
            'has_uts' => $uts !== null,
            'has_uas' => $uas !== null,
            'uts_week_seq' => $uts?->week_seq !== null ? (int) $uts->week_seq : null,
            'uas_week_seq' => $uas?->week_seq !== null ? (int) $uas->week_seq : null,
        ];
    }
}
