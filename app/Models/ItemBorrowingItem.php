<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBorrowingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_borrowing_id',
        'item_id',
        'quantity',
        'borrow_date',
        'return_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'borrow_date' => 'datetime',
        'return_date' => 'datetime',
    ];

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(ItemBorrowing::class, 'item_borrowing_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
