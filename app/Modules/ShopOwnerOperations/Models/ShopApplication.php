<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\ApplicationStatus;
use Database\Factories\ShopApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to open a shop in a mall and the mall's decision (decision D-014).
 */
#[Table(name: 'shop_applications', key: 'shop_application_id')]
#[Fillable(['shop_id', 'mall_id', 'applicant_user_id', 'status', 'message', 'decision_reason', 'decided_by_user_id', 'decided_at'])]
#[UseFactory(ShopApplicationFactory::class)]
class ShopApplication extends Model
{
    /** @use HasFactory<ShopApplicationFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'decided_at' => 'immutable_datetime',
        ];
    }
}
