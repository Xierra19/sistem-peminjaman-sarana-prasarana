<?php

// file: app/Models/CourseExamSchedule.php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories.HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'exam_type',
        'week_seq',
        'exam_date',
        'start_time',
        'end_time',
        'room_id',
    ];

    protected $casts = [
        'exam_date' => 'date',
    ];

    /**
     * @return BelongsTo<CourseOffering, CourseExamSchedule>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * @return BelongsTo<Room, CourseExamSchedule>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public static function computeWeekSeqFor(Semester $semester, string|DateTimeInterface $examDate, string $examType): ?int
    {
        if ($examDate === '' || $examDate === null) {
            return null;
        }

        $date = $examDate instanceof DateTimeInterface
            ? Carbon::instance($examDate)
            : Carbon::parse($examDate);

        if ($examType === 'UTS') {
            $start = $semester->uts_start_date;
            $end = $semester->uts_end_date;
        } elseif ($examType === 'UAS') {
            $start = $semester->uas_start_date;
            $end = $semester->uas_end_date;
        } else {
            return null;
        }

        if (!$start || !$end || !$date->betweenIncluded($start, $end)) {
            return null;
        }

        $offset = $start->copy()->startOfDay()->diffInDays($date->copy()->startOfDay());

        return $offset <= 6 ? 1 : 2;
    }
}
