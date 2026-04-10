<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ItemBorrowing extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'item_id',
        'user_id',
        'borrow_date',
        'return_date',
        'quantity',
        'status',
        'attachment',
        'approved_at',
        'approved_by',
        'returned_at',
        'returned_by',
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'return_date' => 'date',
        'quantity' => 'integer',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, ItemBorrowing>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, ItemBorrowing>
     */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * @return HasMany<ItemBorrowingLog>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ItemBorrowingLog::class)->orderBy('created_at');
    }

    public function scopeOverlappingPeriod(Builder $query, int $itemId, Carbon $start, Carbon $end): Builder
    {
        return $query
            ->where('item_id', $itemId)
            ->whereNotIn('status', ['rejected', 'cancelled', 'returned'])
            ->where('borrow_date', '<=', $end->copy()->endOfDay())
            ->where('return_date', '>=', $start->copy()->startOfDay());
    }
}
