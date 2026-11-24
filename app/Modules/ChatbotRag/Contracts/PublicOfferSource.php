<?php

namespace App\Modules\ChatbotRag\Contracts;

/**
 * The promotions of shops that every visitor may see, as sentences the assistant can quote in a
 * shop's profile. The module that owns promotions implements this and binds it; ChatbotRag only
 * knows the contract, so it never depends on that module. Coupon codes and paused or ended
 * promotions are not public and must not be returned.
 */
interface PublicOfferSource
{
    /**
     * @param  list<string>  $shopIds
     * @return array<string, list<string>> Shop id => one sentence per running public offer; shops without offers are absent
     */
    public function describe(array $shopIds, string $timezone): array;
}
