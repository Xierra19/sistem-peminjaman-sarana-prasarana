<?php

namespace App\Services\Semesters;

use App\DataTransferObjects\Semesters\DuplicateDto;
use App\Models\SemesterCourseDefault;

class ExactDuplicateChecker
{
    /**
     * Periksa duplikat persis per slot.
     *
     * @param array<string,mixed> $data
     */
    public function check(int $semesterId, array $data, ?int $excludeId = null): ?DuplicateDto
    {
        $queryBase = SemesterCourseDefault::query()
            ->where('semester_id', $semesterId)
            ->where('course_code', $data['course_code'])
            ->where('day_of_week', $data['day_of_week']);

        // Teori selalu ada
        $theoryDuplicate = (clone $queryBase)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->where('theory_start_time', $data['theory_start_time'])
            ->where('theory_end_time', $data['theory_end_time'])
            ->where(function ($query) use ($data) {
                if ($data['theory_room_id']) {
                    $query->where('theory_room_id', $data['theory_room_id']);
                } else {
                    $query->whereNull('theory_room_id');
                }
            })
            ->first();

        if ($theoryDuplicate) {
            return new DuplicateDto('Teori', $theoryDuplicate);
        }

        if ($data['practicum1_start_time'] && $data['practicum1_end_time']) {
            $duplicate = (clone $queryBase)
                ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
                ->where('practicum1_start_time', $data['practicum1_start_time'])
                ->where('practicum1_end_time', $data['practicum1_end_time'])
                ->where(function ($query) use ($data) {
                    if ($data['practicum1_room_id']) {
                        $query->where('practicum1_room_id', $data['practicum1_room_id']);
                    } else {
                        $query->whereNull('practicum1_room_id');
                    }
                })
                ->first();

            if ($duplicate) {
                return new DuplicateDto('Praktikum 1', $duplicate);
            }
        }

        if ($data['practicum2_start_time'] && $data['practicum2_end_time']) {
            $duplicate = (clone $queryBase)
                ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
                ->where('practicum2_start_time', $data['practicum2_start_time'])
                ->where('practicum2_end_time', $data['practicum2_end_time'])
                ->where(function ($query) use ($data) {
                    if ($data['practicum2_room_id']) {
                        $query->where('practicum2_room_id', $data['practicum2_room_id']);
                    } else {
                        $query->whereNull('practicum2_room_id');
                    }
                })
                ->first();

            if ($duplicate) {
                return new DuplicateDto('Praktikum 2', $duplicate);
            }
        }

        return null;
    }
}