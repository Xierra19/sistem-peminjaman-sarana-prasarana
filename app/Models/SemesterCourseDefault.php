<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterCourseDefault extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'course_name',
        'course_code',
        'day_of_week',
        'theory_start_time',
        'theory_end_time',
        'theory_room_id',
        'practicum1_start_time',
        'practicum1_end_time',
        'practicum1_room_id',
        'practicum2_start_time',
        'practicum2_end_time',
        'practicum2_room_id',
    ];

    protected $casts = [
        'theory_start_time' => 'datetime:H:i',
        'theory_end_time' => 'datetime:H:i',
        'practicum1_start_time' => 'datetime:H:i',
        'practicum1_end_time' => 'datetime:H:i',
        'practicum2_start_time' => 'datetime:H:i',
        'practicum2_end_time' => 'datetime:H:i',
    ];

    /**
     * Semester induk.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(MasterSemester::class, 'semester_id');
    }

    public function theoryRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'theory_room_id');
    }

    public function practicum1Room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'practicum1_room_id');
    }

    public function practicum2Room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'practicum2_room_id');
    }
}