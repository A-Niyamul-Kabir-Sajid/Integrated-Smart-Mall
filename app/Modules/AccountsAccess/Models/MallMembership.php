<?php

namespace App\Modules\AccountsAccess\Models;

use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MALL_MEMBERSHIP: a mall-scoped role (mall_admin) held for one mall.
 */
#[Table(name: 'mall_memberships', key: 'mall_membership_id', timestamps: false)]
#[Fillable(['user_id', 'mall_id', 'role_id', 'status', 'assigned_at'])]
class MallMembership extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
        ];
    }
}
