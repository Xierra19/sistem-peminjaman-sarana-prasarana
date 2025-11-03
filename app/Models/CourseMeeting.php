<?php

// file: app/Models/CourseMeeting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'meeting_no',
        'meeting_date',
        'room_id',
    ];

    protected $casts = [
        'meeting_date' => 'date',
    ];

    /**
     * @return BelongsTo<CourseOffering, CourseMeeting>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * @return BelongsTo<Room, CourseMeeting>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('meeting_date', $date);
    }
}
