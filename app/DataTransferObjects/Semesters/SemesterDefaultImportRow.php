<?php

namespace App\DataTransferObjects\Semesters;

class SemesterDefaultImportRow
{
    public function __construct(
        public int $lineNumber,
        public string $courseName,
        public string $courseCode,
        public string $theoryTime,
        public ?string $practicum1Time,
        public ?string $practicum2Time,
        public string $dayOfWeek,
        public ?string $theoryRoomCode,
        public ?string $practicum1RoomCode,
        public ?string $practicum2RoomCode,
    ) {
    }

    public function toArray(): array
    {
        return [
            'course_name' => $this->courseName,
            'course_code' => $this->courseCode,
            'theory_time' => $this->theoryTime,
            'practicum1_time' => $this->practicum1Time,
            'practicum2_time' => $this->practicum2Time,
            'day_of_week' => $this->dayOfWeek,
            'theory_room_code' => $this->theoryRoomCode,
            'practicum1_room_code' => $this->practicum1RoomCode,
            'practicum2_room_code' => $this->practicum2RoomCode,
        ];
    }
}