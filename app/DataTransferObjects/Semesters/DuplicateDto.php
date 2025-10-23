<?php

namespace App\DataTransferObjects\Semesters;

use App\Models\SemesterCourseDefault;

class DuplicateDto
{
    public function __construct(
        public string $slotType,
        public SemesterCourseDefault $record
    ) {
    }
}