<?php

namespace App\Modules\ChatbotRag\Models;

use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\DocumentStatus;
use App\Modules\ChatbotRag\Enums\SourceKind;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * KNOWLEDGE_DOCUMENT: one controlled source the assistant may quote, in one mall. Its text is held
 * by its revisions; only the current revision of an active document is ever retrieved.
 */
#[Table(name: 'knowledge_documents', key: 'document_id')]
#[Fillable(['mall_id', 'shop_id', 'shop_product_id', 'source_kind', 'title', 'source_locator', 'access_scope', 'status', 'current_revision_id'])]
#[UseFactory(KnowledgeDocumentFactory::class)]
class KnowledgeDocument extends Model
{
    /** @use HasFactory<KnowledgeDocumentFactory> */
    use HasFactory, HasUuids;

    /**
     * Source locators of documents captured from the mall's own records start with this.
     */
    public const SYSTEM_PREFIX = 'system:';

    /**
     * @return BelongsTo<Mall, $this>
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * Every captured revision, newest first.
     *
     * @return HasMany<DocumentRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class, 'document_id')->orderByDesc('revision_no');
    }

    /**
     * @return BelongsTo<DocumentRevision, $this>
     */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'current_revision_id');
    }

    /**
     * Captured from the mall's records by the sync, so not editable by hand.
     */
    public function isSystemManaged(): bool
    {
        return str_starts_with((string) $this->source_locator, self::SYSTEM_PREFIX);
    }

    #[Scope]
    protected function systemManaged(Builder $query): Builder
    {
        return $query->where('source_locator', 'like', self::SYSTEM_PREFIX.'%');
    }

    #[Scope]
    protected function handWritten(Builder $query): Builder
    {
        return $query->where('source_locator', 'not like', self::SYSTEM_PREFIX.'%');
    }

    protected function casts(): array
    {
        return [
            'source_kind' => SourceKind::class,
            'access_scope' => AccessScope::class,
            'status' => DocumentStatus::class,
        ];
    }
}
