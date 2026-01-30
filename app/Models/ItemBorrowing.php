<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBorrowing extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'user_id',
        'borrow_date',
        'return_date',
        'quantity',
        'status',
        'notes',
    ];

    protected $casts = [
        'borrow_date' => 'datetime',
        'return_date' => 'datetime',
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<User, ItemBorrowing>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Item, ItemBorrowing>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
