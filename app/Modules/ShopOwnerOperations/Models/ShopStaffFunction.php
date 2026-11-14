<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\ShopMembership;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The work function of one staff membership (decision D-014).
 */
#[Table(name: 'shop_staff_functions', key: 'shop_membership_id', keyType: 'string', incrementing: false)]
#[Fillable(['shop_membership_id', 'staff_function'])]
class ShopStaffFunction extends Model
{
    /**
     * @return BelongsTo<ShopMembership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(ShopMembership::class, 'shop_membership_id');
    }

    protected function casts(): array
    {
        return [
            'staff_function' => ShopFunction::class,
        ];
    }
}
