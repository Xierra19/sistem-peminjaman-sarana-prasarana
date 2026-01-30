<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'quantity',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ItemBorrowing>
     */
    public function itemBorrowings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ItemBorrowing::class);
    }
}
