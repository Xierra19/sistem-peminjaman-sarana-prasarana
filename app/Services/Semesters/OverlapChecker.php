<?php

namespace App\Services\Semesters;

use App\DataTransferObjects\Semesters\ConflictDto;
use App\Models\SemesterCourseDefault;

class OverlapChecker
{
    /**
     * Menentukan apakah dua rentang waktu overlap secara ketat.
     */
    public function overlaps(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }

    /**
     * Cek konflik ruangan untuk slot tertentu.
     */
    public function checkRoomOverlap(int $semesterId, int $roomId, string $day, string $start, string $end, ?int $excludeId = null): ?ConflictDto
    {
        $records = SemesterCourseDefault::query()
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $day)
            ->where(function ($query) use ($roomId) {
                $query->where('theory_room_id', $roomId)
                    ->orWhere('practicum1_room_id', $roomId)
                    ->orWhere('practicum2_room_id', $roomId);
            })
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->get();

        /** @var SemesterCourseDefault $record */
        foreach ($records as $record) {
            if ($record->theory_room_id === $roomId && $this->overlaps($start, $end, $record->theory_start_time->format('H:i'), $record->theory_end_time->format('H:i'))) {
                return new ConflictDto('Teori', $day, $record->theory_start_time->format('H:i'), $record->theory_end_time->format('H:i'), $record);
            }

            if ($record->practicum1_room_id === $roomId && $record->practicum1_start_time && $record->practicum1_end_time && $this->overlaps($start, $end, $record->practicum1_start_time->format('H:i'), $record->practicum1_end_time->format('H:i'))) {
                return new ConflictDto('Praktikum 1', $day, $record->practicum1_start_time->format('H:i'), $record->practicum1_end_time->format('H:i'), $record);
            }

            if ($record->practicum2_room_id === $roomId && $record->practicum2_start_time && $record->practicum2_end_time && $this->overlaps($start, $end, $record->practicum2_start_time->format('H:i'), $record->practicum2_end_time->format('H:i'))) {
                return new ConflictDto('Praktikum 2', $day, $record->practicum2_start_time->format('H:i'), $record->practicum2_end_time->format('H:i'), $record);
            }
        }

        return null;
    }
}