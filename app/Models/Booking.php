<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $room_id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string $start_time
 * @property string $end_time
 * @property string $schedule_mode
 * @property string|null $schedule_start_date
 * @property string|null $schedule_end_date
 * @property string|null $schedule_start_clock
 * @property string|null $schedule_end_clock
 * @property string $status
 * @property string $type
 * @property string|null $attachment
 * @property int|null $letter_sequence
 * @property string|null $letter_number
 * @property \Illuminate\Support\Carbon|null $letter_generated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Room $room
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\LogHistory[] $logs
 */
class Booking extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_NEEDS_REVISION = 'needs_revision';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const PENDING_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_PENDING,
        self::STATUS_REQUESTED,
        self::STATUS_NEEDS_REVISION,
    ];

    public const APPROVAL_PENDING_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_PENDING,
        self::STATUS_REQUESTED,
    ];

    public const QUEUED_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_PENDING,
        self::STATUS_REQUESTED,
    ];

    public const BLOCKING_STATUSES = [
        self::STATUS_APPROVED,
    ];

    public const WAITING_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_PENDING,
        self::STATUS_REQUESTED,
        self::STATUS_NEEDS_REVISION,
    ];

    public const INACTIVE_STATUSES = [
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    public const MODE_CONTINUOUS = 'continuous';

    public const MODE_SAME_HOURS_DAILY = 'same_hours_daily';

    protected $fillable = [
        'room_id',
        'user_id',
        'resubmitted_from_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'schedule_mode',
        'schedule_start_date',
        'schedule_end_date',
        'schedule_start_clock',
        'schedule_end_clock',
        'status',
        'type',
        'attachment',
        'letter_sequence',
        'letter_number',
        'letter_generated_at',
    ];

    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
        'schedule_start_date' => 'date:Y-m-d',
        'schedule_end_date' => 'date:Y-m-d',
        'letter_generated_at' => 'datetime',
        'letter_sequence' => 'integer',
    ];

    protected $appends = [
        'schedule_mode_label',
        'schedule_summary',
        'schedule_short_summary',
        'room_summary',
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

    public function resubmittedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmitted_from_id');
    }

    /**
     * @return HasMany<LogHistory>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(LogHistory::class);
    }

    public function roomSchedules(): HasMany
    {
        return $this->hasMany(BookingRoomSchedule::class)->orderBy('start_time')->orderBy('id');
    }

    public function isContinuousSchedule(): bool
    {
        return ($this->schedule_mode ?: self::MODE_CONTINUOUS) === self::MODE_CONTINUOUS;
    }

    public function isSameHoursDailySchedule(): bool
    {
        return $this->schedule_mode === self::MODE_SAME_HOURS_DAILY;
    }

    public function canBeResubmitted(): bool
    {
        return $this->status === self::STATUS_REJECTED
            || ($this->status === self::STATUS_CANCELLED && filled($this->letter_number));
    }

    public function getScheduleModeLabelAttribute(): string
    {
        return $this->isSameHoursDailySchedule()
            ? 'Jam Sama pada Rentang Tanggal'
            : 'Kontinu Antar Hari';
    }

    public function getScheduleStartDateValue(): Carbon
    {
        if ($this->schedule_start_date) {
            return Carbon::parse($this->schedule_start_date)->startOfDay();
        }

        return Carbon::parse($this->start_time)->startOfDay();
    }

    public function getScheduleEndDateValue(): Carbon
    {
        if ($this->schedule_end_date) {
            return Carbon::parse($this->schedule_end_date)->startOfDay();
        }

        return Carbon::parse($this->end_time)->startOfDay();
    }

    public function getScheduleStartClockValue(): string
    {
        return $this->schedule_start_clock ?: Carbon::parse($this->start_time)->format('H:i');
    }

    public function getScheduleEndClockValue(): string
    {
        return $this->schedule_end_clock ?: Carbon::parse($this->end_time)->format('H:i');
    }

    public function getExpirationDateValue(): Carbon
    {
        $latestEnd = $this->relationLoaded('roomSchedules')
            ? $this->roomSchedules->max('end_time')
            : $this->roomSchedules()->max('end_time');

        if ($latestEnd) {
            return Carbon::parse($latestEnd)
                ->setTimezone(\App\Services\ExpirePendingBookings::TIMEZONE)
                ->startOfDay()
                ->addDay();
        }

        return $this->getScheduleEndDateValue()
            ->setTimezone(\App\Services\ExpirePendingBookings::TIMEZONE)
            ->startOfDay()
            ->addDay();
    }

    public function isPastExpirationCutoff(?CarbonInterface $now = null): bool
    {
        $currentTime = ($now ?? now(\App\Services\ExpirePendingBookings::TIMEZONE))
            ->copy()
            ->setTimezone(\App\Services\ExpirePendingBookings::TIMEZONE);

        return $currentTime->gte($this->getExpirationDateValue());
    }

    public function getScheduleSummaryAttribute(): string
    {
        $schedules = $this->relationLoaded('roomSchedules')
            ? $this->roomSchedules
            : $this->roomSchedules()->get();

        if ($schedules->isNotEmpty()) {
            if ($schedules->count() === 1) {
                return $schedules->first()->schedule_summary;
            }

            $first = Carbon::parse($schedules->min('start_time'))->locale('id');
            $last = Carbon::parse($schedules->max('end_time'))->locale('id');

            return sprintf(
                '%d jadwal, %s s.d. %s',
                $schedules->count(),
                $first->translatedFormat('d F Y H:i'),
                $last->translatedFormat('d F Y H:i'),
            );
        }

        $startDate = $this->getScheduleStartDateValue()->locale('id');
        $endDate = $this->getScheduleEndDateValue()->locale('id');

        if ($this->isSameHoursDailySchedule()) {
            return sprintf(
                '%s s.d. %s, pukul %s - %s WIB',
                $startDate->translatedFormat('d F Y'),
                $endDate->translatedFormat('d F Y'),
                $this->getScheduleStartClockValue(),
                $this->getScheduleEndClockValue(),
            );
        }

        $startDateTime = Carbon::parse($this->start_time)->locale('id');
        $endDateTime = Carbon::parse($this->end_time)->locale('id');

        return sprintf(
            '%s WIB s.d. %s WIB',
            $startDateTime->translatedFormat('d F Y H:i'),
            $endDateTime->translatedFormat('d F Y H:i'),
        );
    }

    public function getScheduleShortSummaryAttribute(): string
    {
        $schedules = $this->relationLoaded('roomSchedules')
            ? $this->roomSchedules
            : $this->roomSchedules()->get();

        if ($schedules->isNotEmpty()) {
            if ($schedules->count() === 1) {
                return $schedules->first()->schedule_short_summary;
            }

            return sprintf(
                '%d jadwal · %d ruangan',
                $schedules->count(),
                $schedules->pluck('room_id')->unique()->count(),
            );
        }

        $startDate = $this->getScheduleStartDateValue()->locale('id');
        $endDate = $this->getScheduleEndDateValue()->locale('id');

        if ($this->isSameHoursDailySchedule()) {
            return sprintf(
                '%s - %s, %s - %s',
                $startDate->translatedFormat('d M Y'),
                $endDate->translatedFormat('d M Y'),
                $this->getScheduleStartClockValue(),
                $this->getScheduleEndClockValue(),
            );
        }

        $startDateTime = Carbon::parse($this->start_time)->locale('id');
        $endDateTime = Carbon::parse($this->end_time)->locale('id');

        return sprintf(
            '%s - %s',
            $startDateTime->translatedFormat('d M Y H:i'),
            $endDateTime->translatedFormat('d M Y H:i'),
        );
    }

    public function getRoomSummaryAttribute(): string
    {
        $schedules = $this->relationLoaded('roomSchedules')
            ? $this->roomSchedules
            : $this->roomSchedules()->with('room:id,name')->get();

        $roomNames = $schedules
            ->pluck('room.name')
            ->filter()
            ->unique()
            ->values();

        if ($roomNames->isNotEmpty()) {
            return $roomNames->join(', ');
        }

        return $this->room?->name ?? '-';
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    public function buildDailyIntervalsWithinRange(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if ($this->isSameHoursDailySchedule()) {
            return $this->buildSameHoursDailyIntervals($rangeStart, $rangeEnd);
        }

        return $this->buildContinuousIntervals($rangeStart, $rangeEnd);
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function buildContinuousIntervals(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $bookingStart = Carbon::parse($this->start_time);
        $bookingEnd = Carbon::parse($this->end_time);

        $segmentStart = $bookingStart->greaterThan($rangeStart) ? $bookingStart->copy() : $rangeStart->copy();
        $segmentEnd = $bookingEnd->lessThan($rangeEnd) ? $bookingEnd->copy() : $rangeEnd->copy();

        if ($segmentStart->gte($segmentEnd)) {
            return [];
        }

        $intervals = [];
        $period = \Carbon\CarbonPeriod::create($segmentStart->copy()->startOfDay(), $segmentEnd->copy()->startOfDay());

        foreach ($period as $day) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $intervalStart = $segmentStart->greaterThan($dayStart) ? $segmentStart->copy() : $dayStart;
            $intervalEnd = $segmentEnd->lessThan($dayEnd) ? $segmentEnd->copy() : $dayEnd;

            if ($intervalStart->gte($intervalEnd)) {
                continue;
            }

            $intervals[] = [
                'date' => $day->toDateString(),
                'id' => $this->id,
                'title' => $this->title,
                'status' => $this->status,
                'start' => $intervalStart->format('H:i'),
                'end' => $intervalEnd->format('H:i'),
            ];
        }

        return $intervals;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function buildSameHoursDailyIntervals(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $rangeStartDate = $rangeStart->copy()->startOfDay();
        $rangeEndDate = $rangeEnd->copy()->startOfDay();
        $bookingStartDate = $this->getScheduleStartDateValue();
        $bookingEndDate = $this->getScheduleEndDateValue();

        $periodStart = $bookingStartDate->greaterThan($rangeStartDate) ? $bookingStartDate->copy() : $rangeStartDate;
        $periodEnd = $bookingEndDate->lessThan($rangeEndDate) ? $bookingEndDate->copy() : $rangeEndDate;

        if ($periodStart->gt($periodEnd)) {
            return [];
        }

        $intervals = [];
        $startClock = $this->getScheduleStartClockValue();
        $endClock = $this->getScheduleEndClockValue();
        $period = \Carbon\CarbonPeriod::create($periodStart, $periodEnd);

        foreach ($period as $day) {
            $intervalStart = Carbon::parse($day->format('Y-m-d').' '.$startClock);
            $intervalEnd = Carbon::parse($day->format('Y-m-d').' '.$endClock);

            if ($intervalStart->gte($intervalEnd)) {
                continue;
            }

            if ($intervalStart->lt($rangeStart)) {
                $intervalStart = $rangeStart->copy();
            }

            if ($intervalEnd->gt($rangeEnd)) {
                $intervalEnd = $rangeEnd->copy();
            }

            if ($intervalStart->gte($intervalEnd)) {
                continue;
            }

            $intervals[] = [
                'date' => $day->toDateString(),
                'id' => $this->id,
                'title' => $this->title,
                'status' => $this->status,
                'start' => $intervalStart->format('H:i'),
                'end' => $intervalEnd->format('H:i'),
            ];
        }

        return $intervals;
    }

    /**
     * Scope bookings that overlap the provided time range for a room.
     */
    public function scopeOverlap(Builder $query, int $roomId, string $date, string $start, string $end): Builder
    {
        return $query
            ->where('room_id', $roomId)
            ->whereDate('start_time', $date)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);
    }
}
