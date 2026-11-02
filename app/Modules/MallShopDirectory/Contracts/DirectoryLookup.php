<?php

namespace App\Modules\MallShopDirectory\Contracts;

use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use Illuminate\Support\Collection;

/**
 * Read-only directory facts other modules depend on. Changes to directory data
 * go through ShopService, MallService and FloorService, never through this contract.
 *
 * Consumers: ProductsCatalogue (which shops may sell, which are visible),
 * MapsNavigation (floors and shop locations), ShopOwnerOperations and
 * MallOwnerAdmin (the shop or mall being worked on).
 */
interface DirectoryLookup
{
    /**
     * The active mall with this slug, or null for drafts, archived and unknown malls.
     */
    public function activeMallBySlug(string $slug): ?Mall;

    /**
     * Any shop by id regardless of status, for workspaces that manage it; null when unknown.
     */
    public function shop(string $shopId): ?Shop;

    /**
     * Whether the shop may offer products to customers: it exists and is active.
     */
    public function isShopOperational(string $shopId): bool;

    /**
     * Ids of the mall's shops customers may see (active or closed).
     *
     * @return list<string>
     */
    public function listedShopIds(string $mallId): array;

    /**
     * The mall's active floors in display order.
     *
     * @return Collection<int, Floor>
     */
    public function floorsOf(string $mallId): Collection;

    /**
     * The shop's current (non-archived) units with their floors, for map entrances.
     *
     * @return Collection<int, ShopUnit>
     */
    public function locationsOf(string $shopId): Collection;
}
