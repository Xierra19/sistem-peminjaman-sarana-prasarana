<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OtpCode extends Model
{
    use HasFactory;

    public const CONTEXT_REGISTRATION = 'registration';
    public const CONTEXT_RESET_PASSWORD = 'reset_password';
    public const CHANNEL_EMAIL = 'email';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'identifier',
        'context',
        'channel',
        'code_hash',
        'token_hash',
        'attempts',
        'max_attempts',
        'send_count',
        'last_sent_at',
        'expires_at',
        'consumed_at',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('consumed_at')
            ->where('expires_at', '>', Carbon::now());
    }
}
