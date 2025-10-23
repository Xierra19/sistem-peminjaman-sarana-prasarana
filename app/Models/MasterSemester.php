<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterSemester extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'term',
        'is_active',
        'anchor_date',
        'start_date',
        'end_date',
        'uts_week',
        'uas_week',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'anchor_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Default jadwal yang dimiliki semester ini.
     */
    public function courseDefaults(): HasMany
    {
        return $this->hasMany(SemesterCourseDefault::class, 'semester_id');
    }
}