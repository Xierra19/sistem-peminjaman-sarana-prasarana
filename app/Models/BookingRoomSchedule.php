<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRoomSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'room_id',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime:Y-m-d H:i:s',
        'end_time' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = [
        'schedule_summary',
        'schedule_short_summary',
        'display_start_date',
        'display_end_date',
        'display_start_time',
        'display_end_time',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function getScheduleSummaryAttribute(): string
    {
        $start = Carbon::parse($this->start_time)->locale('id');
        $end = Carbon::parse($this->end_time)->locale('id');

        if (! $start->isSameDay($end)) {
            return sprintf(
                '%s WIB s.d. %s WIB',
                $start->translatedFormat('d F Y H:i'),
                $end->translatedFormat('d F Y H:i'),
            );
        }

        return sprintf(
            '%s, pukul %s - %s WIB',
            $start->translatedFormat('d F Y'),
            $start->format('H:i'),
            $end->format('H:i'),
        );
    }

    public function getScheduleShortSummaryAttribute(): string
    {
        $start = Carbon::parse($this->start_time)->locale('id');
        $end = Carbon::parse($this->end_time);

        if (! $start->isSameDay($end)) {
            return sprintf(
                '%s - %s',
                $start->translatedFormat('d M Y H:i'),
                $end->locale('id')->translatedFormat('d M Y H:i'),
            );
        }

        return sprintf(
            '%s, %s - %s',
            $start->translatedFormat('d M Y'),
            $start->format('H:i'),
            $end->format('H:i'),
        );
    }

    public function getDisplayStartDateAttribute(): string
    {
        return Carbon::parse($this->start_time)->toDateString();
    }

    public function getDisplayEndDateAttribute(): string
    {
        return Carbon::parse($this->end_time)->toDateString();
    }

    public function getDisplayStartTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i');
    }

    public function getDisplayEndTimeAttribute(): string
    {
        return Carbon::parse($this->end_time)->format('H:i');
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    public function buildDailyIntervalsWithinRange(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $scheduleStart = Carbon::parse($this->start_time);
        $scheduleEnd = Carbon::parse($this->end_time);
        $segmentStart = $scheduleStart->greaterThan($rangeStart) ? $scheduleStart->copy() : $rangeStart->copy();
        $segmentEnd = $scheduleEnd->lessThan($rangeEnd) ? $scheduleEnd->copy() : $rangeEnd->copy();

        if ($segmentStart->gte($segmentEnd)) {
            return [];
        }

        $intervals = [];

        foreach (CarbonPeriod::create($segmentStart->copy()->startOfDay(), $segmentEnd->copy()->startOfDay()) as $day) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();
            $intervalStart = $segmentStart->greaterThan($dayStart) ? $segmentStart->copy() : $dayStart;
            $intervalEnd = $segmentEnd->lessThan($dayEnd) ? $segmentEnd->copy() : $dayEnd;

            if ($intervalStart->gte($intervalEnd)) {
                continue;
            }

            $intervals[] = [
                'date' => $day->toDateString(),
                'id' => $this->booking_id,
                'title' => $this->booking?->title ?? 'Booking',
                'status' => $this->booking?->status ?? Booking::STATUS_WAITING,
                'start' => $intervalStart->format('H:i'),
                'end' => $intervalEnd->format('H:i'),
            ];
        }

        return $intervals;
    }
}
