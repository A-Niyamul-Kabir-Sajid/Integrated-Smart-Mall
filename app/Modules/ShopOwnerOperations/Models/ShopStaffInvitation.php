<?php

namespace App\Modules\ShopOwnerOperations\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\InvitationStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use Database\Factories\ShopStaffInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offer of staff work at a shop. Only the SHA-256 hash of the invitation secret
 * is stored, and it never appears in serialised output.
 */
#[Table(name: 'shop_staff_invitations', key: 'shop_staff_invitation_id')]
#[Fillable(['shop_id', 'email', 'staff_function', 'secret_hash', 'invited_by_user_id', 'status', 'expires_at', 'accepted_by_user_id', 'accepted_at'])]
#[Hidden(['secret_hash'])]
#[UseFactory(ShopStaffInvitationFactory::class)]
class ShopStaffInvitation extends Model
{
    /** @use HasFactory<ShopStaffInvitationFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isUsable(): bool
    {
        return $this->status === InvitationStatus::Pending && $this->expires_at->isFuture();
    }

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'staff_function' => ShopFunction::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
