<?php

namespace App\Modules\FavouritesPreferences\Models;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Enums\RouteMode;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * USER_PREFERENCE: one optional settings row per account.
 */
#[Table(name: 'user_preferences', key: 'user_id', keyType: 'string', incrementing: false)]
#[Fillable([
    'user_id', 'default_mall_id', 'locale', 'route_mode', 'ar_preferred',
    'personalization_opt_in', 'notify_by_email', 'notify_by_sms',
])]
class UserPreference extends Model
{
    public const CREATED_AT = null;

    /**
     * Application defaults, used while a user has no stored row.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'default_mall_id' => null,
        'locale' => 'en',
        'route_mode' => 'shortest_distance',
        'ar_preferred' => false,
        'personalization_opt_in' => false,
        'notify_by_email' => true,
        'notify_by_sms' => false,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = self::DEFAULTS;

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
    public function defaultMall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'default_mall_id');
    }

    protected function casts(): array
    {
        return [
            'route_mode' => RouteMode::class,
            'ar_preferred' => 'boolean',
            'personalization_opt_in' => 'boolean',
            'notify_by_email' => 'boolean',
            'notify_by_sms' => 'boolean',
        ];
    }
}
