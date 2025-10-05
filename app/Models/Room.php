<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'building_id',
        'capacity',
    ];

    /**
     * Get the building that owns the room.
     */
    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * Get the bookings for the room.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}