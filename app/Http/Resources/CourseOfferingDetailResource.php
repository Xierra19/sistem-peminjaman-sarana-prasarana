<?php

// file: app/Http/Resources/CourseOfferingDetailResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CourseOffering
 */
class CourseOfferingDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $semester = $this->semester;
        $examSchedules = $this->courseExamSchedules ?? collect();
        $meetings = $this->courseMeetings ?? collect();

        return [
            'offering' => [
                'id' => $this->id,
                'course_code' => $this->course_code,
                'course_name' => $this->course_name,
                'class_group' => $this->class_group,
            ],
            'meetings' => collect(range(1, 14))->map(function (int $number) use ($meetings) {
                $meeting = $meetings->firstWhere('meeting_no', $number);

                return [
                    'meeting_no' => $number,
                    'meeting_date' => $meeting?->meeting_date?->format('Y-m-d'),
                    'room' => $meeting && $meeting->room ? [
                        'id' => $meeting->room->id,
                        'code' => $meeting->room->code ?? null,
                        'name' => $meeting->room->name,
                    ] : null,
                ];
            })->all(),
            'exams' => [
                'uts' => $this->formatExamPayload($examSchedules->firstWhere('exam_type', 'UTS')),
                'uas' => $this->formatExamPayload($examSchedules->firstWhere('exam_type', 'UAS')),
            ],
            'ranges' => $semester
                ? [
                    'uts' => $semester->dateRangeStrings()['uts'] ?? null,
                    'uas' => $semester->dateRangeStrings()['uas'] ?? null,
                ]
                : [
                    'uts' => null,
                    'uas' => null,
                ],
        ];
    }

    /**
     * @param  \App\Models\CourseExamSchedule|null  $exam
     * @return array<string, mixed>
     */
    protected function formatExamPayload($exam): array
    {
        return [
            'exam_type' => $exam?->exam_type,
            'exam_date' => $exam?->exam_date?->format('Y-m-d'),
            'week_seq' => $exam?->week_seq !== null ? (int) $exam->week_seq : null,
            'start_time' => $exam?->start_time,
            'end_time' => $exam?->end_time,
            'room_code' => $exam?->room?->code ?? null,
            'room' => $exam && $exam->room ? [
                'id' => $exam->room->id,
                'code' => $exam->room->code ?? null,
                'name' => $exam->room->name,
            ] : null,
        ];
    }
}
