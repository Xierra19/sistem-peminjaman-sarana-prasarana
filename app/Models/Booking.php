<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'title',
        'description',
        'date',
        'start_time',
        'end_time',
        'status',
        'type',
        'attachment',
        'letter_sequence',
        'letter_number',
        'letter_generated_at',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'letter_generated_at' => 'datetime',
        'letter_sequence' => 'integer',
    ];

    /**
     * @return BelongsTo<User, Booking>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Room, Booking>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<LogHistory>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(LogHistory::class);
    }

    /**
     * Scope bookings that overlap the provided time range for a room.
     */
    public function scopeOverlap(Builder $query, int $roomId, string $date, string $start, string $end): Builder
    {
        return $query
            ->where('room_id', $roomId)
            ->where('date', $date)
            ->whereIn('status', ['waiting', 'approved'])
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);
    }
}
