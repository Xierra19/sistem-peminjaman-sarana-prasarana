<?php

// file: app/Models/CourseExamSchedule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'exam_type',
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

}
