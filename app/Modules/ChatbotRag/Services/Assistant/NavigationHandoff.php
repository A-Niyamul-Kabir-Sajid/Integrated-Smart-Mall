<?php

namespace App\Modules\ChatbotRag\Services\Assistant;

use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\FacilityService;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;

/**
 * Hands a destination named in an answer over to navigation (Jira ISM-133). An answer offers
 * "directions" links for the shops and facilities it mentions; opening one checks again that the
 * destination exists, is public and belongs to this mall, and only then sends the visitor on.
 *
 * This class is the single seam to MapsNavigation. Until that module (EPIC-05) provides routes, the
 * destination page is the shop's page, which shows its floor and unit, or the facility finder on the
 * facility's floor. When routes exist, only destinationUrl() changes.
 */
class NavigationHandoff
{
    public const SHOP = 'shop';

    public const FACILITY = 'facility';

    public const LISTING = 'listing';

    public const TYPES = [self::SHOP, self::FACILITY, self::LISTING];

    public function __construct(
        private DirectoryLookup $directory,
        private FacilityService $facilities,
        private CatalogueLookup $catalogue,
    ) {}

    /**
     * @return array{type: string, id: string, label: string, detail: string|null}
     */
    public function forShop(Shop $shop): array
    {
        $units = $shop->units->where('status', '!=', 'archived');

        return [
            'type' => self::SHOP,
            'id' => (string) $shop->getKey(),
            'label' => $shop->name,
            'detail' => $units->isEmpty() ? null : $units->map(fn (ShopUnit $unit): string => $unit->floor->name.', unit '.$unit->unit_code)->implode('; '),
        ];
    }

    /**
     * @return array{type: string, id: string, label: string, detail: string|null}
     */
    public function forFacility(Facility $facility): array
    {
        return [
            'type' => self::FACILITY,
            'id' => (string) $facility->getKey(),
            'label' => $facility->name,
            'detail' => $facility->floor->name.(filled($facility->location_note) ? ', '.$facility->location_note : ''),
        ];
    }

    /**
     * Destinations for the listed shops an answer names, so that an answer written by a model links
     * to directions just like one built from a list.
     *
     * @param  list<string>  $alreadyLinked  Shop ids that have a destination already
     * @return list<array{type: string, id: string, label: string, detail: string|null}>
     */
    public function shopsMentionedIn(Mall $mall, string $text, array $alreadyLinked = []): array
    {
        $haystack = mb_strtolower($text);
        $found = [];

        foreach ($this->directory->listedShops((string) $mall->getKey()) as $shop) {
            $name = mb_strtolower(trim($shop->name));

            if (mb_strlen($name) < 3 || in_array((string) $shop->getKey(), $alreadyLinked, true)) {
                continue;
            }

            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/u', $haystack) === 1) {
                $found[] = $this->forShop($shop);
            }
        }

        return $found;
    }

    /**
     * Where a destination leads, or null when it does not exist, is not public or belongs to
     * another mall.
     */
    public function destinationUrl(Mall $mall, string $type, string $id): ?string
    {
        return match ($type) {
            self::SHOP => $this->shopUrl($mall, $this->directory->shop($id)),
            self::LISTING => $this->shopUrl($mall, $this->catalogue->visibleListing($mall, $id)?->shop),
            self::FACILITY => $this->facilityUrl($mall, $id),
            default => null,
        };
    }

    private function shopUrl(Mall $mall, ?Shop $shop): ?string
    {
        if ($shop === null || $shop->mall_id !== $mall->getKey() || ! in_array($shop->status, [ShopStatus::Active, ShopStatus::Closed], true)) {
            return null;
        }

        return route('shops.show', ['publicMall' => $mall->slug, 'publicShop' => $shop->slug]);
    }

    private function facilityUrl(Mall $mall, string $id): ?string
    {
        $facility = $this->facilities->find($mall)->first(fn (Facility $facility): bool => $facility->getKey() === $id);

        return $facility === null ? null : route('facilities.index', [
            'publicMall' => $mall->slug, 'type' => $facility->facility_type->value, 'floor' => $facility->floor->code,
        ]);
    }
}
