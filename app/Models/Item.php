<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Direct borrowings (legacy)
     */
    public function itemBorrowings(): HasMany
    {
        return $this->hasMany(ItemBorrowing::class);
    }

    /**
     * Borrowings via pivot (new multi)
     */
    public function borrowingItems(): HasMany
    {
        return $this->hasMany(ItemBorrowingItem::class);
    }

}
