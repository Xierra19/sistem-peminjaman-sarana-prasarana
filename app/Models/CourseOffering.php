<?php

// file: app/Models/CourseOffering.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseOffering extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'course_code',
        'course_name',
        'class_group',
    ];

    /**
     * @return BelongsTo<Semester, CourseOffering>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return HasMany<CourseMeeting>
     */
    public function courseMeetings(): HasMany
    {
        return $this->hasMany(CourseMeeting::class);
    }

    /**
     * @return HasMany<CourseExamSchedule>
     */
    public function courseExamSchedules(): HasMany
    {
        return $this->hasMany(CourseExamSchedule::class);
    }
}
