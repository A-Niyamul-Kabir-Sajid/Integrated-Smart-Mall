<?php

namespace App\Modules\ChatbotRag\Services\Knowledge;

use App\Modules\ChatbotRag\Contracts\PublicOfferSource;

/**
 * Used when no module provides public offers: shop profiles are captured without an offers section.
 */
class NoPublicOffers implements PublicOfferSource
{
    public function describe(array $shopIds, string $timezone): array
    {
        return [];
    }
}
