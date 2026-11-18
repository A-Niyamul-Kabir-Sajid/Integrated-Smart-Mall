<?php

namespace App\Modules\ChatbotRag\Services\Knowledge;

use App\Modules\ChatbotRag\Contracts\PublicOfferSource;
use App\Modules\ChatbotRag\Enums\DocumentStatus;
use App\Modules\ChatbotRag\Enums\SourceKind;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\MallShopDirectory\Contracts\DirectoryLookup;
use App\Modules\MallShopDirectory\Enums\FacilityStatus;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\DirectoryService;
use App\Modules\MallShopDirectory\Services\FacilityService;
use App\Modules\MallShopDirectory\Services\OpeningStatusService;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Support\Collection;

/**
 * Captures the mall's public records as knowledge documents (Jira ISM-130, decision D-018): one
 * document for the floors and their facilities, one per listed shop (profile, location, weekly
 * opening hours, public offers) and one per product offer customers can buy.
 *
 * Only what a visitor can already see on the public pages is read, through the services of the
 * modules that own it: listed shops, customer-visible facilities, visible offers and promotions that
 * need no code. Draft and archived records, private notes, stock counts and sales never enter the
 * store. Prices and stock are left out of the text on purpose: an answer reads them live.
 *
 * Running it twice changes nothing; a record that changed gets a new revision; a record that is no
 * longer public has its document withdrawn, and restored when it becomes public again.
 */
class KnowledgeSyncService
{
    /**
     * A mall with more listed shops than this would need the sync to read them in pages.
     */
    private const SHOP_LIMIT = 1000;

    private const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function __construct(
        private KnowledgeService $knowledge,
        private DirectoryService $directory,
        private DirectoryLookup $lookup,
        private FacilityService $facilities,
        private OpeningStatusService $openingStatus,
        private CatalogueLookup $catalogue,
        private PublicOfferSource $offers,
    ) {}

    /**
     * @return array{created: int, updated: int, unchanged: int, withdrawn: int}
     */
    public function syncMall(Mall $mall): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'withdrawn' => 0];
        $existing = KnowledgeDocument::query()->whereBelongsTo($mall)->systemManaged()->get()->keyBy('source_locator');
        $wanted = $this->wantedDocuments($mall);

        foreach ($wanted as $locator => $document) {
            $outcome = $this->knowledge->storeSystemDocument($mall, [
                'source_kind' => $document['kind'],
                'source_locator' => $locator,
                'title' => $document['title'],
                'shop_id' => $document['shop_id'] ?? null,
                'shop_product_id' => $document['shop_product_id'] ?? null,
            ], $document['content'], $existing->get($locator));

            $result[$outcome]++;
        }

        foreach ($existing as $locator => $document) {
            if (! isset($wanted[$locator]) && $document->status === DocumentStatus::Active) {
                $this->knowledge->withdrawSystemDocument($document);
                $result['withdrawn']++;
            }
        }

        return $result;
    }

    /**
     * @return array<string, array{kind: SourceKind, title: string, content: string, shop_id?: string, shop_product_id?: string}> Keyed by source locator
     */
    private function wantedDocuments(Mall $mall): array
    {
        $shops = $this->directory->browseShops($mall, [], self::SHOP_LIMIT)->getCollection();
        $offers = $this->offers->describe(
            $shops->where('status', ShopStatus::Active)->map(fn (Shop $shop): string => (string) $shop->getKey())->values()->all(),
            $mall->timezone,
        );

        $documents = [
            KnowledgeDocument::SYSTEM_PREFIX.'floors' => [
                'kind' => SourceKind::MallGuide,
                'title' => 'Floors and facilities of '.$mall->name,
                'content' => $this->floorGuide($mall, $shops),
            ],
        ];

        foreach ($shops as $shop) {
            $documents[KnowledgeDocument::SYSTEM_PREFIX.'shop:'.$shop->getKey()] = [
                'kind' => SourceKind::ShopProfile,
                'title' => $shop->name,
                'content' => $this->shopProfile($mall, $shop, $offers[(string) $shop->getKey()] ?? []),
                'shop_id' => (string) $shop->getKey(),
            ];
        }

        foreach ($this->catalogue->visibleListings($mall) as $listing) {
            $documents[KnowledgeDocument::SYSTEM_PREFIX.'listing:'.$listing->getKey()] = [
                'kind' => SourceKind::ProductListing,
                'title' => $this->listingTitle($listing),
                'content' => $this->listingText($listing),
                'shop_id' => (string) $listing->shop_id,
                'shop_product_id' => (string) $listing->getKey(),
            ];
        }

        return $documents;
    }

    /**
     * @param  Collection<int, Shop>  $shops
     */
    private function floorGuide(Mall $mall, Collection $shops): string
    {
        $floors = $this->lookup->floorsOf((string) $mall->getKey());
        $facilities = $this->facilities->find($mall)->groupBy('floor_id');
        $lines = [
            '# Floors of '.$mall->name,
            $floors->isEmpty()
                ? $mall->name.' has not published its floors yet.'
                : $mall->name.' has '.$floors->count().' '.($floors->count() === 1 ? 'floor' : 'floors').': '.$floors->map(fn (Floor $floor): string => $floor->name.' ('.$floor->code.')')->implode(', ').'.',
        ];

        foreach ($floors as $floor) {
            $onFloor = $shops->filter(fn (Shop $shop): bool => $shop->units->contains(fn (ShopUnit $unit): bool => $unit->status !== 'archived' && $unit->floor_id === $floor->getKey()));

            $lines[] = '';
            $lines[] = '# '.$floor->name.' ('.$floor->code.')';
            $lines[] = $onFloor->isEmpty()
                ? 'No shop is listed on '.$floor->name.'.'
                : 'Shops on '.$floor->name.': '.$onFloor->map(fn (Shop $shop): string => $shop->name)->sort()->implode(', ').'.';

            foreach ($facilities->get($floor->getKey(), []) as $facility) {
                $lines[] = $this->facilityLine($facility, $floor);
            }
        }

        return implode("\n", $lines);
    }

    private function facilityLine(Facility $facility, Floor $floor): string
    {
        $line = $facility->name.' ('.$facility->facility_type->label().') is on '.$floor->name;

        if (filled($facility->location_note)) {
            $line .= ': '.$facility->location_note;
        }

        $line .= $facility->is_accessible ? '. Step-free access.' : '. No step-free access.';

        return $facility->status === FacilityStatus::TemporarilyClosed ? $line.' Temporarily closed.' : $line;
    }

    /**
     * @param  list<string>  $offers
     */
    private function shopProfile(Mall $mall, Shop $shop, array $offers): string
    {
        $units = $shop->units->where('status', '!=', 'archived');
        $lines = [$shop->name.' is a '.$shop->category->name.' shop at '.$mall->name.'.'];

        if (filled($shop->description)) {
            $lines[] = trim((string) $shop->description);
        }

        $lines[] = $units->isEmpty()
            ? 'Its location is not published.'
            : 'Location of '.$shop->name.': '.$units->map(fn (ShopUnit $unit): string => $unit->floor->name.' ('.$unit->floor->code.'), unit '.$unit->unit_code)->implode('; ').'.';

        if ($shop->status === ShopStatus::Closed) {
            $lines[] = $shop->name.' is closed at the moment and takes no orders.';

            return implode("\n", $lines);
        }

        $lines[] = '';
        $lines[] = '# Opening hours of '.$shop->name;

        foreach ($this->openingStatus->weeklySchedule($shop->openingHours) as $weekday => $intervals) {
            $lines[] = self::WEEKDAYS[$weekday].': '.($intervals === [] ? 'closed' : implode(', ', $intervals)).'.';
        }

        if ($offers !== []) {
            $lines[] = '';
            $lines[] = '# Offers at '.$shop->name;
            array_push($lines, ...$offers);
        }

        return implode("\n", $lines);
    }

    private function listingTitle(ShopProduct $listing): string
    {
        $product = $listing->variant->product;
        $option = $listing->variant->label === 'Standard' ? '' : ' ('.$listing->variant->label.')';

        return $product->name.$option.' at '.$listing->shop->name;
    }

    private function listingText(ShopProduct $listing): string
    {
        $product = $listing->variant->product;
        $units = $listing->shop->units->where('status', '!=', 'archived');
        $lines = [$product->name.(filled($product->brand) ? ' by '.$product->brand : '').' is a product in the category '.$product->category->name.'.'];

        if (filled($product->description)) {
            $lines[] = trim((string) $product->description);
        }

        $lines[] = 'Option: '.$listing->variant->label.'.';
        $lines[] = 'Sold by '.$listing->shop->name.($units->isEmpty() ? '.' : ' on '.$units->map(fn (ShopUnit $unit): string => $unit->floor->name.', unit '.$unit->unit_code)->implode('; ').'.');

        return implode("\n", $lines);
    }
}
