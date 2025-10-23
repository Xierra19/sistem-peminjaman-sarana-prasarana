<?php

namespace App\Services\Semesters;

use App\DataTransferObjects\Semesters\ConflictDto;
use App\Models\SemesterCourseDefault;

class OverlapChecker
{
    private function fmt($v): ?string
    {
        if ($v === null) return null;
        if ($v instanceof \DateTimeInterface) {
            return $v->format('H:i');
        }
        $s = (string) $v;
        return substr($s, 0, 5);
    }
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
            $rTheStart = $this->fmt($record->theory_start_time);
            $rTheEnd = $this->fmt($record->theory_end_time);
            if ($record->theory_room_id === $roomId && $rTheStart && $rTheEnd && $this->overlaps($start, $end, $rTheStart, $rTheEnd)) {
                return new ConflictDto('Teori', $day, $rTheStart, $rTheEnd, $record);
            }

            $rP1Start = $this->fmt($record->practicum1_start_time);
            $rP1End = $this->fmt($record->practicum1_end_time);
            if ($record->practicum1_room_id === $roomId && $rP1Start && $rP1End && $this->overlaps($start, $end, $rP1Start, $rP1End)) {
                return new ConflictDto('Praktikum 1', $day, $rP1Start, $rP1End, $record);
            }

            $rP2Start = $this->fmt($record->practicum2_start_time);
            $rP2End = $this->fmt($record->practicum2_end_time);
            if ($record->practicum2_room_id === $roomId && $rP2Start && $rP2End && $this->overlaps($start, $end, $rP2Start, $rP2End)) {
                return new ConflictDto('Praktikum 2', $day, $rP2Start, $rP2End, $record);
            }
        }

        return null;
    }
}
