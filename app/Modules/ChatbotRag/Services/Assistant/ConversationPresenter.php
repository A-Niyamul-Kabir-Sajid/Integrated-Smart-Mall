<?php

namespace App\Modules\ChatbotRag\Services\Assistant;

use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\DocumentStatus;
use App\Modules\ChatbotRag\Enums\MessageRole;
use App\Modules\ChatbotRag\Models\ChatMessage;
use App\Modules\ChatbotRag\Models\Conversation;
use App\Modules\ChatbotRag\Models\MessageCitation;
use App\Modules\ChatbotRag\Models\MessageRecommendation;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\ProductsCatalogue\Contracts\CatalogueLookup;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use Illuminate\Support\Collection;

/**
 * Prepares a conversation for the screen. Two things are decided here again, at the moment of
 * display and not when the answer was given:
 *
 * - A cited source is shown only if the reader may still see it. A document that has since been
 *   withdrawn or restricted loses its excerpt; one that has changed is marked as an earlier version.
 * - A recommended offer shows the price and availability the catalogue holds now; an offer that is
 *   no longer on sale is shown as such.
 */
class ConversationPresenter
{
    public const CURRENT = 'current';

    public const SUPERSEDED = 'superseded';

    public const WITHDRAWN = 'withdrawn';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(private ConversationService $conversations, private CatalogueLookup $catalogue) {}

    /**
     * @return list<array<string, mixed>> One entry per message, oldest first
     */
    public function present(?Conversation $conversation, Mall $mall, AccessScope $reader): array
    {
        if ($conversation === null) {
            return [];
        }

        $messages = $this->conversations->messages($conversation);
        $listings = $this->catalogue->visibleListings($mall, $messages
            ->flatMap(fn (ChatMessage $message) => $message->recommendations->pluck('shop_product_id'))
            ->unique()->values()->all())
            ->keyBy(fn (ShopProduct $listing): string => (string) $listing->getKey());

        return $messages->map(fn (ChatMessage $message): array => $this->message($message, $mall, $reader, $listings))->all();
    }

    /**
     * @param  Collection<string, ShopProduct>  $listings
     * @return array<string, mixed>
     */
    private function message(ChatMessage $message, Mall $mall, AccessScope $reader, Collection $listings): array
    {
        $meta = $message->meta ?? [];

        return [
            'id' => (string) $message->getKey(),
            'isAssistant' => $message->role === MessageRole::Assistant,
            'text' => $message->content,
            'time' => $message->created_at->timezone($mall->timezone),
            'outcome' => $meta['outcome'] ?? null,
            'driver' => $meta['driver'] ?? null,
            'model' => $message->model_label,
            'citations' => $message->citations->map(fn (MessageCitation $citation): array => $this->citation($citation, $mall, $reader))->all(),
            'liveSources' => $meta['live_sources'] ?? [],
            'offers' => $message->recommendations->map(fn (MessageRecommendation $recommendation): array => $this->offer($recommendation, $mall, $listings))->all(),
            'handoffs' => array_map(fn (array $handoff): array => [
                'label' => $handoff['label'],
                'detail' => $handoff['detail'] ?? null,
                'url' => route('assistant.go', ['publicMall' => $mall->slug, 'type' => $handoff['type'], 'id' => $handoff['id']]),
            ], $meta['handoffs'] ?? []),
            'notes' => $meta['notes'] ?? [],
            'links' => $meta['links'] ?? [],
            'actions' => $meta['actions'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function citation(MessageCitation $citation, Mall $mall, AccessScope $reader): array
    {
        $revision = $citation->chunk->revision;
        $document = $revision->document;
        $readable = $document->mall_id === $mall->getKey() && in_array($document->access_scope->value, $reader->readableScopes(), true);

        $state = match (true) {
            ! $readable => self::UNAVAILABLE,
            $document->status === DocumentStatus::Withdrawn => self::WITHDRAWN,
            $document->current_revision_id !== $revision->getKey() => self::SUPERSEDED,
            default => self::CURRENT,
        };
        $showText = in_array($state, [self::CURRENT, self::SUPERSEDED], true);

        return [
            'number' => $citation->citation_no,
            'state' => $state,
            'title' => $readable ? $document->title : null,
            'heading' => $showText ? $citation->chunk->heading : null,
            'kind' => $readable ? $document->source_kind->label() : null,
            'excerpt' => $showText ? $citation->quoted_excerpt : null,
            'revision' => $revision->revision_no,
            'captured' => $revision->captured_at->timezone($mall->timezone),
        ];
    }

    /**
     * @param  Collection<string, ShopProduct>  $listings
     * @return array<string, mixed>
     */
    private function offer(MessageRecommendation $recommendation, Mall $mall, Collection $listings): array
    {
        $listing = $listings->get((string) $recommendation->shop_product_id);

        if ($listing === null) {
            return ['gone' => true, 'reason' => $recommendation->reason];
        }

        $product = $listing->variant->product;
        $units = $listing->shop->units->where('status', '!=', 'archived');

        return [
            'gone' => false,
            'reason' => $recommendation->reason,
            'product' => $product->name,
            'option' => $listing->variant->label === 'Standard' ? null : $listing->variant->label,
            'shop' => $listing->shop->name,
            'price' => number_format((float) $listing->price_amount, 2),
            'currency' => $listing->currency,
            'available' => $listing->availability_status === 'available',
            'location' => $units->isEmpty() ? null : $units->map(fn (ShopUnit $unit): string => $unit->floor->name.', '.__('Unit :code', ['code' => $unit->unit_code]))->implode('; '),
            'productUrl' => route('products.show', ['publicMall' => $mall->slug, 'publicProduct' => $product->getKey()]),
            'directionsUrl' => route('assistant.go', ['publicMall' => $mall->slug, 'type' => NavigationHandoff::LISTING, 'id' => $listing->getKey()]),
        ];
    }
}
