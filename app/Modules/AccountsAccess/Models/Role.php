<?php

namespace App\Modules\AccountsAccess\Models;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Enums\RoleScope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ROLE: reference data created by the roles migration; never edited at runtime.
 */
#[Table(name: 'roles', key: 'role_id', timestamps: false)]
class Role extends Model
{
    use HasUuids;

    public static function forCode(RoleCode $code): self
    {
        return static::query()->where('code', $code->value)->firstOrFail();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')->withPivot('assigned_at');
    }

    protected function casts(): array
    {
        return [
            'code' => RoleCode::class,
            'scope_type' => RoleScope::class,
        ];
    }
}
