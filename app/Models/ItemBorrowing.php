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
        'user_id',
        'status',
        'attachment',
        'signed_letter',
        'signed_letter_uploaded_at',
        'approved_at',
        'approved_by',
        'returned_at',
        'returned_by',
        // Legacy single-item fields
        'item_id',
        'quantity',
        'borrow_date',
        'return_date',
    ];

    protected $casts = [
        'signed_letter_uploaded_at' => 'datetime',
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
     * @return HasMany<ItemBorrowingItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItemBorrowingItem::class);
    }

    /**
     * Legacy single item support (existing data)
     */
    public function singleItem(): BelongsTo
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

    /**
     * Updated scope for new pivot structure
     */
    public function scopeOverlappingPeriod(Builder $query, int $itemId, Carbon $start, Carbon $end): Builder
    {
        return $query->whereHas('items', function ($q) use ($itemId, $start, $end) {
            $q->where('item_id', $itemId)
              ->where('borrow_date', '<=', $end->copy()->endOfDay())
              ->where('return_date', '>=', $start->copy()->startOfDay());
        })->whereNotIn('status', ['rejected', 'cancelled', 'returned']);
    }

}
