<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailQuotaCounter extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'day_date',
        'sent_count',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'day_date' => 'date',
    ];
}
