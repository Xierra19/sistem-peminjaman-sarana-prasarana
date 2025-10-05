<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'campus_id',
    ];

    /**
     * Get the campus that owns the building.
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Get the rooms for the building.
     */
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}