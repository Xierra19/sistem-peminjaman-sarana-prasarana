<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccountStatusLog extends Model
{
    public const ACTION_ACTIVATED = 'activated';

    public const ACTION_DEACTIVATED = 'deactivated';

    protected $fillable = [
        'user_id',
        'actor_id',
        'action',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
