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
        'uts_exam_date',
        'uts_start_time',
        'uts_end_time',
        'uts_room_id',
        'uas_exam_date',
        'uas_start_time',
        'uas_end_time',
        'uas_room_id',
    ];

    protected $casts = [
        // Simpan sebagai string agar aman untuk kolom TIME
        'theory_start_time' => 'string',
        'theory_end_time' => 'string',
        'practicum1_start_time' => 'string',
        'practicum1_end_time' => 'string',
        'practicum2_start_time' => 'string',
        'practicum2_end_time' => 'string',
        'uts_exam_date' => 'date',
        'uts_start_time' => 'string',
        'uts_end_time' => 'string',
        'uas_exam_date' => 'date',
        'uas_start_time' => 'string',
        'uas_end_time' => 'string',
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

    public function utsRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'uts_room_id');
    }

    public function uasRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'uas_room_id');
    }
}
