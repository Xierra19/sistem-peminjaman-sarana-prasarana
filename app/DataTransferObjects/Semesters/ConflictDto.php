<?php

namespace App\DataTransferObjects\Semesters;

use App\Models\SemesterCourseDefault;

class ConflictDto
{
    public function __construct(
        public string $slotType,
        public string $day,
        public string $start,
        public string $end,
        public SemesterCourseDefault $record,
    ) {
    }
}