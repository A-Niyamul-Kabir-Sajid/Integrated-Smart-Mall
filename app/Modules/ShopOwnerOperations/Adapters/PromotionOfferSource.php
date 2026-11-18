<?php

namespace App\Modules\ShopOwnerOperations\Adapters;

use App\Modules\ChatbotRag\Contracts\PublicOfferSource;
use App\Modules\ShopOwnerOperations\Enums\DiscountType;
use App\Modules\ShopOwnerOperations\Models\ShopPromotion;

/**
 * Tells the assistant which offers of a shop every visitor may see: the running promotions that
 * need no coupon code, the same ones the shop page shows. Coupon codes are never described.
 */
class PromotionOfferSource implements PublicOfferSource
{
    public function describe(array $shopIds, string $timezone): array
    {
        if ($shopIds === []) {
            return [];
        }

        $offers = [];

        $promotions = ShopPromotion::query()
            ->whereIn('shop_id', $shopIds)
            ->whereNull('coupon_code')
            ->where('is_paused', false)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->orderBy('ends_at')
            ->orderBy('shop_promotion_id')
            ->get()
            ->filter(fn (ShopPromotion $promotion): bool => $promotion->isRunning());

        foreach ($promotions as $promotion) {
            $offers[(string) $promotion->shop_id][] = $this->sentence($promotion, $timezone);
        }

        return $offers;
    }

    private function sentence(ShopPromotion $promotion, string $timezone): string
    {
        $discount = $promotion->discount_type === DiscountType::Percent
            ? rtrim(rtrim((string) $promotion->discount_value, '0'), '.').'% off'
            : 'BDT '.number_format((float) $promotion->discount_value, 2).' off';

        $sentence = $promotion->title.': '.$discount;

        if ((float) $promotion->min_subtotal > 0) {
            $sentence .= ' on orders of BDT '.number_format((float) $promotion->min_subtotal, 2).' or more';
        }

        $sentence .= ', until '.$promotion->ends_at->timezone($timezone)->format('j F Y').'.';

        return filled($promotion->description) ? $sentence.' '.trim((string) $promotion->description) : $sentence;
    }
}
