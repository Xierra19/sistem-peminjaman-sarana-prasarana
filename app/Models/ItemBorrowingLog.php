<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBorrowingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_borrowing_id',
        'user_id',
        'action',
        'description',
    ];

    /**
     * @return BelongsTo<ItemBorrowing, ItemBorrowingLog>
     */
    public function itemBorrowing(): BelongsTo
    {
        return $this->belongsTo(ItemBorrowing::class);
    }

    /**
     * @return BelongsTo<User, ItemBorrowingLog>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
