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

    protected $appends = [
        'effective_status',
    ];

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
        'borrow_date' => 'datetime',
        'return_date' => 'datetime',
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
        return $query
            ->where('item_id', $itemId)
            ->where('borrow_date', '<', $end)
            ->where('return_date', '>', $start)
            ->whereNotIn('status', ['rejected', 'cancelled', 'returned']);
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'returned') {
            return 'completed';
        }

        if ($this->status !== 'approved') {
            return $this->status === 'requested' ? 'waiting' : $this->status;
        }

        $latestReturn = $this->relationLoaded('items')
            ? $this->items->max('return_date')
            : $this->items()->max('return_date');

        $latestReturn ??= $this->return_date;

        return $latestReturn && Carbon::parse($latestReturn)->lte(now())
            ? 'completed'
            : 'approved';
    }

}
