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
