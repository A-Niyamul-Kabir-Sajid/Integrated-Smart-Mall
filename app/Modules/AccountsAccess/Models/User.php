<?php

namespace App\Modules\AccountsAccess\Models;

use App\Core\Services\PhoneNumberNormalizer;
use App\Modules\AccountsAccess\Enums\AccountStatus;
use App\Modules\AccountsAccess\Enums\RoleCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * APP_USER: one account for customers, shop staff and mall administrators (decision D-008).
 */
#[Table(name: 'users', key: 'user_id')]
#[Fillable(['display_name', 'email', 'phone', 'password_hash'])]
#[Hidden(['password_hash', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * The ERD column holding the password hash.
     *
     * @var string
     */
    protected $authPasswordName = 'password_hash';

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /**
     * Platform-scoped roles held directly (USER_ROLE).
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')->withPivot('assigned_at');
    }

    public function hasPlatformRole(RoleCode $code): bool
    {
        return $this->roles()->where('code', $code->value)->exists();
    }

    /**
     * @return HasMany<MallMembership, $this>
     */
    public function mallMemberships(): HasMany
    {
        return $this->hasMany(MallMembership::class, 'user_id');
    }

    /**
     * @return HasMany<ShopMembership, $this>
     */
    public function shopMemberships(): HasMany
    {
        return $this->hasMany(ShopMembership::class, 'user_id');
    }

    /**
     * Whether the account holds one of the roles through an active membership of the mall.
     */
    public function hasMallRole(string $mallId, RoleCode ...$codes): bool
    {
        return $this->mallMemberships()->active()
            ->where('mall_id', $mallId)
            ->whereHas('role', fn ($role) => $role->whereIn('code', array_map(fn (RoleCode $code): string => $code->value, $codes)))
            ->exists();
    }

    /**
     * Whether the account holds one of the roles through an active membership of the shop.
     */
    public function hasShopRole(string $shopId, RoleCode ...$codes): bool
    {
        return $this->shopMemberships()->active()
            ->where('shop_id', $shopId)
            ->whereHas('role', fn ($role) => $role->whereIn('code', array_map(fn (RoleCode $code): string => $code->value, $codes)))
            ->exists();
    }

    /**
     * Phone number used by the sms notification channel.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    /**
     * Emails are stored trimmed and lower-case so uniqueness ignores case.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => blank($value) ? null : Str::lower(trim($value)),
        );
    }

    /**
     * Phones are stored in E.164 when they can be normalised.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => blank($value)
                ? null
                : (app(PhoneNumberNormalizer::class)->normalize($value) ?? trim($value)),
        );
    }

    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password_hash' => 'hashed',
        ];
    }
}
