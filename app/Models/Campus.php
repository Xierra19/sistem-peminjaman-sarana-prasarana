<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
    ];

    /**
     * Get all of the buildings that belong to the campus.
     */
    public function buildings()
    {
        return $this->hasMany(Building::class);
    }
}