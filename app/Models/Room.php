<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'building_id',
        'capacity',
        'is_available',
        'features',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'features' => 'array',
    ];

    /**
     * @return BelongsTo<Building, Room>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * @return HasMany<Booking>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<ImportedClassSchedule>
     */
    public function importedClassSchedules(): HasMany
    {
        return $this->hasMany(ImportedClassSchedule::class);
    }
}
