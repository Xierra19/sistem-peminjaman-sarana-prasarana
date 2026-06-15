<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemBorrowing extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_NEEDS_REVISION = 'needs_revision';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_COMPLETED = 'completed';

    public const CANCELLABLE_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_NEEDS_REVISION,
    ];

    public const EDITABLE_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_NEEDS_REVISION,
    ];

    public const FINAL_STATUSES = [
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_RETURNED,
    ];

    public const NON_BLOCKING_STATUSES = [
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_RETURNED,
    ];

    protected $appends = [
        'effective_status',
    ];

    protected $fillable = [
        'title',
        'description',
        'user_id',
        'resubmitted_from_id',
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
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * @return BelongsTo<User, ItemBorrowing>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function resubmittedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmitted_from_id');
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
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES);
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === self::STATUS_RETURNED) {
            return self::STATUS_COMPLETED;
        }

        if ($this->status !== self::STATUS_APPROVED) {
            return $this->status === self::STATUS_REQUESTED ? self::STATUS_WAITING : $this->status;
        }

        $latestReturn = $this->relationLoaded('items')
            ? $this->items->max('return_date')
            : $this->items()->max('return_date');

        $latestReturn ??= $this->return_date;

        return $latestReturn && Carbon::parse($latestReturn)->lte(now())
            ? self::STATUS_COMPLETED
            : self::STATUS_APPROVED;
    }
}
