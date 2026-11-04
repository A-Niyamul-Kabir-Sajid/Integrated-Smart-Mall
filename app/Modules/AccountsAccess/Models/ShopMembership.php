<?php

namespace App\Modules\AccountsAccess\Models;

use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SHOP_MEMBERSHIP: a shop-scoped role (shop_owner, shop_staff) held for one shop.
 */
#[Table(name: 'shop_memberships', key: 'shop_membership_id', timestamps: false)]
#[Fillable(['user_id', 'shop_id', 'role_id', 'status', 'assigned_at'])]
class ShopMembership extends Model
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
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
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
