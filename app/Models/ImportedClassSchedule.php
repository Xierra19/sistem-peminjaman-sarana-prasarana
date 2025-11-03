<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedClassSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'course_code',
        'class_name',
        'date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    /**
     * @return BelongsTo<Room, ImportedClassSchedule>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Scope schedules that overlap the provided time range for a room.
     */
    public function scopeOverlapRoomDateRange(Builder $query, int $roomId, string $date, string $start, string $end): Builder
    {
        return $query
            ->where('room_id', $roomId)
            ->where('date', $date)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);
    }
}
