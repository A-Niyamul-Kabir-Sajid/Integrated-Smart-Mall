<?php

namespace App\Modules\ChatbotRag\Services\Assistant\Tools;

use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\DTOs\SourceSnippetData;
use App\Modules\ChatbotRag\DTOs\ToolResultData;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Services\Assistant\NavigationHandoff;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\DirectoryService;

/**
 * Answers "which shops are open now" from the directory's opening hours at this moment in the
 * mall's time zone. Opening hours quoted from a document can be an hour old; this cannot.
 */
class OpenShopsTool
{
    public const LIMIT = 8;

    public function __construct(private DirectoryService $directory, private NavigationHandoff $handoff) {}

    public function run(Mall $mall, string $language): ToolResultData
    {
        $open = $this->directory->browseShops($mall, ['open_now' => true], 100)->getCollection();
        $clock = now($mall->timezone)->format('H:i');

        if ($open->isEmpty()) {
            return new ToolResultData(
                outcome: AnswerOutcome::Answered,
                fixedText: __('No shop is open right now (:time by the mall\'s clock).', ['time' => $clock], $language),
                meta: ['checked_at' => $clock],
            );
        }

        $shown = $open->take(self::LIMIT)->values();
        $sources = [];
        $handoffs = [];

        foreach ($shown as $index => $shop) {
            $sources[] = new SourceSnippetData($index + 1, SourceSnippetData::DIRECTORY, 'Shop directory', $this->line($shop));
            $handoffs[] = $this->handoff->forShop($shop);
        }

        return new ToolResultData(
            outcome: AnswerOutcome::Answered,
            sources: $sources,
            shape: GenerationRequestData::LISTING,
            lead: __('Shops open right now, at :time by the mall\'s clock (:shown of :count):', ['time' => $clock, 'shown' => $shown->count(), 'count' => $open->count()], $language),
            handoffs: $handoffs,
            meta: ['checked_at' => $clock, 'more' => $open->count() - $shown->count()],
        );
    }

    private function line(Shop $shop): string
    {
        $units = $shop->units->where('status', '!=', 'archived');

        return $shop->name.' ('.$shop->category->name.'): '.($units->isEmpty()
            ? 'location not published'
            : $units->map(fn (ShopUnit $unit): string => $unit->floor->name.', unit '.$unit->unit_code)->implode('; ')).'. Open now.';
    }
}
