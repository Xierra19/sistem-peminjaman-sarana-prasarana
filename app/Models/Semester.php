<?php

// file: app/Models/Semester.php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Semester extends Model
{
    use HasFactory;

    /**
     * Allow mass assignment for all attributes.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Attribute casting definitions.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'uts_start_date' => 'date',
        'uts_end_date' => 'date',
        'uas_start_date' => 'date',
        'uas_end_date' => 'date',
        'teaching_1_7_start_date' => 'date',
        'teaching_1_7_end_date' => 'date',
        'teaching_8_14_start_date' => 'date',
        'teaching_8_14_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Scope query to active semesters.
     */
    public function scopeActive(Builder $query): Builder
    {
        if (Schema::hasColumn($this->getTable(), 'is_active')) {
            return $query->where('is_active', true);
        }

        return $query;
    }

    /**
     * Determine if the given date falls within the UTS window.
     */
    public function isUtsDate(DateTimeInterface|string $date): bool
    {
        if (!$this->uts_start_date || !$this->uts_end_date) {
            return false;
        }

        $carbonDate = $this->resolveCarbon($date);

        return $carbonDate->betweenIncluded($this->uts_start_date, $this->uts_end_date);
    }

    /**
     * Determine if the given date falls within the UAS window.
     */
    public function isUasDate(DateTimeInterface|string $date): bool
    {
        if (!$this->uas_start_date || !$this->uas_end_date) {
            return false;
        }

        $carbonDate = $this->resolveCarbon($date);

        return $carbonDate->betweenIncluded($this->uas_start_date, $this->uas_end_date);
    }

    /**
     * Produce human-readable date ranges for the semester and exam windows.
     *
     * @return array<string, string|null>
     */
    public function dateRangeStrings(): array
    {
        return [
            'semester' => $this->formatRange($this->start_date, $this->end_date),
            'uts' => $this->formatRange($this->uts_start_date, $this->uts_end_date),
            'uas' => $this->formatRange($this->uas_start_date, $this->uas_end_date),
            'teaching_1_7' => $this->formatRange($this->teaching_1_7_start_date, $this->teaching_1_7_end_date),
            'teaching_8_14' => $this->formatRange($this->teaching_8_14_start_date, $this->teaching_8_14_end_date),
        ];
    }

    /**
     * Resolve various date inputs into a Carbon instance.
     */
    protected function resolveCarbon(DateTimeInterface|string $date): Carbon
    {
        return $date instanceof DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);
    }

    /**
     * Format a date range into the expected string representation.
     */
    protected function formatRange(?Carbon $start, ?Carbon $end): ?string
    {
        if (!$start || !$end) {
            return null;
        }

        return $start->format('Y-m-d') . ' s.d. ' . $end->format('Y-m-d');
    }
}
