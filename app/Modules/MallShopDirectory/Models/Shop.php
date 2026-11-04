<?php

namespace App\Modules\MallShopDirectory\Models;

use App\Core\Services\PhoneNumberNormalizer;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SHOP: one business in one mall; its physical locations are SHOP_UNIT rows.
 */
#[Table(name: 'shops', key: 'shop_id')]
#[Fillable(['mall_id', 'shop_category_id', 'slug', 'name', 'description', 'contact_phone', 'status'])]
#[UseFactory(ShopFactory::class)]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<ShopCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'shop_category_id');
    }

    /**
     * @return HasMany<ShopUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ShopUnit::class, 'shop_id');
    }

    /**
     * @return HasMany<ShopOpeningHour, $this>
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(ShopOpeningHour::class, 'shop_id')
            ->orderBy('weekday')
            ->orderBy('interval_no');
    }

    /**
     * Shops a customer may see in the public directory (active or closed).
     */
    #[Scope]
    protected function listed(Builder $query): Builder
    {
        return $query->whereIn('status', [ShopStatus::Active->value, ShopStatus::Closed->value]);
    }

    /**
     * Contact numbers are stored in E.164 when they can be normalised.
     */
    protected function contactPhone(): Attribute
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
            'status' => ShopStatus::class,
        ];
    }
}
