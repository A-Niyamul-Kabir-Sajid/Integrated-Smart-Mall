<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\DiscountType;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Models\PromotionRedemption;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Models\ShopPromotion;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Promotions and coupons of one shop (Jira ISM-107/ISM-109, decision D-014).
 *
 * Staff with the promotions area (managers and owners) create and manage them. A promotion
 * without a code applies to every order while it runs, the best one winning; a promotion with
 * a code applies when the customer enters it. Redemptions are tied to the order that used
 * them and given back when that order is declined, cancelled or expires.
 */
class PromotionService
{
    public const MAX_PERCENT = 90;

    public const MAX_AMOUNT_OFF = 100000;

    public const MAX_DAYS = 730;

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Shop $shop, array $data): ShopPromotion
    {
        $this->authorize($actor, $shop);
        $attributes = $this->clean($shop, $data, null);

        try {
            $promotion = ShopPromotion::query()->create([...$attributes, 'shop_id' => $shop->getKey(), 'created_by_user_id' => $actor->getKey()]);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation('promotion.code_taken', __('Another promotion of this shop already uses that coupon code.'));
        }

        $this->audit->record('promotion.created', $promotion, ['title' => $promotion->title, 'code' => $promotion->coupon_code], $shop->mall_id, $shop->getKey());

        return $promotion;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, ShopPromotion $promotion, array $data): ShopPromotion
    {
        $shop = $promotion->loadMissing('shop')->shop;
        $this->authorize($actor, $shop);

        try {
            $promotion->update($this->clean($shop, $data, $promotion));
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation('promotion.code_taken', __('Another promotion of this shop already uses that coupon code.'));
        }

        $this->audit->record('promotion.updated', $promotion, ['title' => $promotion->title], $shop->mall_id, $shop->getKey());

        return $promotion;
    }

    public function pause(User $actor, ShopPromotion $promotion): ShopPromotion
    {
        return $this->setPaused($actor, $promotion, true);
    }

    public function resume(User $actor, ShopPromotion $promotion): ShopPromotion
    {
        return $this->setPaused($actor, $promotion, false);
    }

    /**
     * Ends the promotion now; its history and redemptions stay.
     */
    public function end(User $actor, ShopPromotion $promotion): ShopPromotion
    {
        $shop = $promotion->loadMissing('shop')->shop;
        $this->authorize($actor, $shop);

        if ($promotion->ends_at->isPast()) {
            throw new BusinessRuleViolation('promotion.already_ended', __('This promotion already ended.'));
        }

        $promotion->update(['ends_at' => now(), 'starts_at' => $promotion->starts_at->isFuture() ? now()->subSecond() : $promotion->starts_at]);
        $this->audit->record('promotion.ended', $promotion, [], $shop->mall_id, $shop->getKey());

        return $promotion;
    }

    /**
     * The shop's promotions, newest first, for its staff.
     *
     * @return Collection<int, ShopPromotion>
     */
    public function listForShop(User $actor, Shop $shop): Collection
    {
        $this->authorize($actor, $shop);

        return ShopPromotion::query()->where('shop_id', $shop->getKey())->latest('created_at')->get();
    }

    /**
     * The offers every visitor may see: running promotions that need no coupon code.
     *
     * @return Collection<int, ShopPromotion>
     */
    public function publicOffers(Shop $shop): Collection
    {
        return ShopPromotion::query()->where('shop_id', $shop->getKey())->whereNull('coupon_code')
            ->where('is_paused', false)->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->orderBy('ends_at')->get()
            ->filter(fn (ShopPromotion $promotion): bool => $promotion->isRunning())->values();
    }

    /**
     * The discount an order of this subtotal gets, with the promotion that gives it. A coupon
     * code that is wrong, expired, used up or already used by the customer is refused with one
     * generic message for unknown codes; without a code the best running storewide offer applies.
     *
     * @return array{promotion: ShopPromotion, discount: int}|null
     */
    public function quote(Shop $shop, User $customer, int $subtotalCents, ?string $coupon): ?array
    {
        $code = Str::upper(trim((string) $coupon));

        if ($code !== '') {
            $promotion = ShopPromotion::query()->where('shop_id', $shop->getKey())->where('coupon_code', $code)->first();

            if ($promotion === null || ! $promotion->isRunning()) {
                throw new BusinessRuleViolation('promotion.invalid_code', __('This coupon code is not valid right now.'));
            }

            if ($subtotalCents < $this->cents($promotion->min_subtotal)) {
                throw new BusinessRuleViolation('promotion.min_subtotal', __('This coupon needs an order of at least ৳:amount.', ['amount' => (string) $promotion->min_subtotal]));
            }

            if ($this->usedBy($promotion, $customer) >= $promotion->per_customer_limit) {
                throw new BusinessRuleViolation('promotion.already_used', __('You have already used this coupon.'));
            }

            return ['promotion' => $promotion, 'discount' => $this->discountFor($promotion, $subtotalCents)];
        }

        $best = null;

        foreach (ShopPromotion::query()->where('shop_id', $shop->getKey())->whereNull('coupon_code')->get() as $promotion) {
            if (! $promotion->isRunning() || $subtotalCents < $this->cents($promotion->min_subtotal)) {
                continue;
            }

            $discount = $this->discountFor($promotion, $subtotalCents);

            if ($discount > ($best['discount'] ?? 0)) {
                $best = ['promotion' => $promotion, 'discount' => $discount];
            }
        }

        return $best;
    }

    /**
     * Counts one use of the promotion for the order, inside the order's transaction.
     */
    public function redeem(ShopPromotion $promotion, ShopOrder $order, User $customer, int $discountCents): void
    {
        $locked = ShopPromotion::query()->lockForUpdate()->findOrFail($promotion->getKey());

        if (! $locked->isRunning()) {
            throw new BusinessRuleViolation('promotion.invalid_code', __('This coupon code is not valid right now.'));
        }

        if ($this->usedBy($locked, $customer) >= $locked->per_customer_limit) {
            throw new BusinessRuleViolation('promotion.already_used', __('You have already used this coupon.'));
        }

        $locked->increment('times_redeemed');

        PromotionRedemption::query()->create([
            'shop_promotion_id' => $locked->getKey(),
            'shop_order_id' => $order->getKey(),
            'user_id' => $customer->getKey(),
            'discount_amount' => number_format($discountCents / 100, 2, '.', ''),
            'created_at' => now(),
        ]);
    }

    /**
     * Gives the promotion its use back when the order that used it will never be collected.
     */
    public function release(ShopOrder $order): void
    {
        $redemption = PromotionRedemption::query()->where('shop_order_id', $order->getKey())->whereNull('released_at')->lockForUpdate()->first();

        if ($redemption === null) {
            return;
        }

        $redemption->update(['released_at' => now()]);
        ShopPromotion::query()->whereKey($redemption->shop_promotion_id)->where('times_redeemed', '>', 0)->decrement('times_redeemed');
    }

    private function setPaused(User $actor, ShopPromotion $promotion, bool $paused): ShopPromotion
    {
        $shop = $promotion->loadMissing('shop')->shop;
        $this->authorize($actor, $shop);

        if ($promotion->ends_at->isPast()) {
            throw new BusinessRuleViolation('promotion.already_ended', __('This promotion already ended.'));
        }

        $promotion->update(['is_paused' => $paused]);
        $this->audit->record($paused ? 'promotion.paused' : 'promotion.resumed', $promotion, [], $shop->mall_id, $shop->getKey());

        return $promotion;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clean(Shop $shop, array $data, ?ShopPromotion $existing): array
    {
        $title = trim((string) ($data['title'] ?? ''));

        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) {
            throw new BusinessRuleViolation('promotion.invalid_title', __('Give the promotion a title of 3 to 120 characters.'));
        }

        $type = DiscountType::tryFrom((string) ($data['discount_type'] ?? ''))
            ?? throw new BusinessRuleViolation('promotion.invalid_type', __('Choose percent off or an amount off.'));

        $value = $data['discount_value'] ?? null;

        if (! is_numeric($value) || (float) $value <= 0 || ($type === DiscountType::Percent && (float) $value > self::MAX_PERCENT) || ($type === DiscountType::AmountOff && (float) $value > self::MAX_AMOUNT_OFF)) {
            throw new BusinessRuleViolation('promotion.invalid_value', $type === DiscountType::Percent
                ? __('Enter a percentage above 0 and up to :max.', ['max' => self::MAX_PERCENT])
                : __('Enter an amount above 0 and up to :max.', ['max' => self::MAX_AMOUNT_OFF]));
        }

        $code = Str::upper(trim((string) ($data['coupon_code'] ?? '')));

        if ($code !== '' && preg_match('/^[A-Z0-9][A-Z0-9_-]{2,29}$/', $code) !== 1) {
            throw new BusinessRuleViolation('promotion.invalid_code_format', __('A coupon code uses 3 to 30 letters, digits, hyphens or underscores.'));
        }

        $min = $data['min_subtotal'] ?? 0;

        if (! is_numeric($min) || (float) $min < 0 || (float) $min > 9999999) {
            throw new BusinessRuleViolation('promotion.invalid_minimum', __('Enter a minimum order of zero or more.'));
        }

        $timezone = (string) $shop->loadMissing('mall')->mall->timezone;

        try {
            $starts = CarbonImmutable::parse((string) ($data['starts_at'] ?? ''), $timezone)->utc();
            $ends = CarbonImmutable::parse((string) ($data['ends_at'] ?? ''), $timezone)->utc();
        } catch (\Throwable) {
            throw new BusinessRuleViolation('promotion.invalid_dates', __('Enter a start and an end date.'));
        }

        if ($ends->lessThanOrEqualTo($starts) || $ends->greaterThan(now()->addDays(self::MAX_DAYS))) {
            throw new BusinessRuleViolation('promotion.invalid_dates', __('The end must be after the start and within :days days from now.', ['days' => self::MAX_DAYS]));
        }

        $usageLimit = ($data['usage_limit'] ?? '') === '' || ($data['usage_limit'] ?? null) === null ? null : $data['usage_limit'];

        if ($usageLimit !== null && (! is_numeric($usageLimit) || (int) $usageLimit < 1 || (int) $usageLimit > 1000000)) {
            throw new BusinessRuleViolation('promotion.invalid_limit', __('The total number of uses must be 1 or more, or empty for no limit.'));
        }

        $perCustomer = $data['per_customer_limit'] ?? 1;

        if (! is_numeric($perCustomer) || (int) $perCustomer < 1 || (int) $perCustomer > 10) {
            throw new BusinessRuleViolation('promotion.invalid_limit', __('Uses per customer must be from 1 to 10.'));
        }

        if ($existing !== null && $usageLimit !== null && (int) $usageLimit < $existing->times_redeemed) {
            throw new BusinessRuleViolation('promotion.invalid_limit', __('The total number of uses cannot be lower than the :count already used.', ['count' => $existing->times_redeemed]));
        }

        return [
            'title' => $title,
            'description' => filled($data['description'] ?? null) ? mb_substr(trim($data['description']), 0, 1000) : null,
            'discount_type' => $type,
            'discount_value' => number_format((float) $value, 2, '.', ''),
            'coupon_code' => $code === '' ? null : $code,
            'min_subtotal' => number_format((float) $min, 2, '.', ''),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'usage_limit' => $usageLimit === null ? null : (int) $usageLimit,
            'per_customer_limit' => (int) $perCustomer,
        ];
    }

    private function discountFor(ShopPromotion $promotion, int $subtotalCents): int
    {
        $discount = $promotion->discount_type === DiscountType::Percent
            ? intdiv($subtotalCents * (int) round(((float) $promotion->discount_value) * 100), 10000)
            : $this->cents($promotion->discount_value);

        return max(0, min($discount, $subtotalCents));
    }

    private function usedBy(ShopPromotion $promotion, User $customer): int
    {
        return PromotionRedemption::query()->where('shop_promotion_id', $promotion->getKey())->where('user_id', $customer->getKey())->whereNull('released_at')->count();
    }

    private function cents(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function authorize(User $actor, Shop $shop): void
    {
        Gate::forUser($actor)->authorize(ShopArea::Promotions->ability(), $shop);
    }
}
