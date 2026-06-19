<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @method bool isAdmin()
 * @method bool isSuperAdmin()
 * @method bool isAdminBap()
 * @method bool isAdminSarpras()
 * @method bool canManageUsers()
 * @method bool canManageHistory()
 * @method bool canManageRoomModule()
 * @method bool canManageItemModule()
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'is_active' => true,
    ];

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN_BAP = 'admin_bap';

    public const ROLE_ADMIN_SARPRAS = 'admin_sarpras';

    public const ROLE_USER = 'user';

    public const ROLE_LEGACY_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nim',
        'email',
        'phone',
        'role',
        'is_active',
        'deactivated_at',
        'deactivation_reason',
        'deactivated_by',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function roomAdminRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN_BAP,
            self::ROLE_LEGACY_ADMIN,
        ];
    }

    public static function itemAdminRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN_SARPRAS,
            self::ROLE_LEGACY_ADMIN,
        ];
    }

    public static function adminRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN_BAP,
            self::ROLE_ADMIN_SARPRAS,
            self::ROLE_LEGACY_ADMIN,
        ];
    }

    public function normalizedRole(): string
    {
        return $this->role === self::ROLE_LEGACY_ADMIN
            ? self::ROLE_SUPER_ADMIN
            : (string) $this->role;
    }

    public function hasRole(string $role): bool
    {
        $normalizedRole = $this->normalizedRole();
        $requestedRole = $role === self::ROLE_LEGACY_ADMIN
            ? self::ROLE_SUPER_ADMIN
            : $role;

        return $normalizedRole === $requestedRole;
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isAdminBap(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN_BAP);
    }

    public function isAdminSarpras(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN_SARPRAS);
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(self::adminRoles());
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageHistory(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageRoomModule(): bool
    {
        return $this->hasAnyRole(self::roomAdminRoles());
    }

    public function canManageItemModule(): bool
    {
        return $this->hasAnyRole(self::itemAdminRoles());
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deactivated_by');
    }

    public function accountStatusLogs(): HasMany
    {
        return $this->hasMany(UserAccountStatusLog::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function itemBorrowings(): HasMany
    {
        return $this->hasMany(ItemBorrowing::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
