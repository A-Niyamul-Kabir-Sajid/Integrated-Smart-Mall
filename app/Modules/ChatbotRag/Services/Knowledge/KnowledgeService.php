<?php

namespace App\Modules\ChatbotRag\Services\Knowledge;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\DocumentStatus;
use App\Modules\ChatbotRag\Enums\SourceKind;
use App\Modules\ChatbotRag\Models\DocumentRevision;
use App\Modules\ChatbotRag\Models\KnowledgeChunk;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Maintains the knowledge documents of a mall (Jira ISM-128, decision D-018): what the assistant may
 * quote, to whom, and in which version.
 *
 * Text is never edited in place. Saving changed text captures a new immutable revision, cuts it into
 * chunks and makes it the current one in the same transaction, so retrieval moves from the old text
 * to the new at once and earlier answers keep the revision they cited. Saving unchanged text captures
 * nothing. A withdrawn document leaves retrieval immediately and can be restored.
 */
class KnowledgeService
{
    public const PER_PAGE = 20;

    public function __construct(
        private DocumentParser $parser,
        private Chunker $chunker,
        private TextAnalyzer $analyzer,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{title: string, source_kind: string, access_scope: string, content: string, source_reference?: string|null}  $data
     */
    public function create(User $actor, Mall $mall, array $data): KnowledgeDocument
    {
        $this->authorize($actor, $mall);
        $kind = $this->writableKind($data['source_kind']);

        return DB::transaction(function () use ($mall, $data, $kind): KnowledgeDocument {
            $document = KnowledgeDocument::query()->create([
                'mall_id' => $mall->getKey(),
                'source_kind' => $kind,
                'title' => trim($data['title']),
                'source_locator' => $this->locator($data['source_reference'] ?? null),
                'access_scope' => $this->scopeFor($kind, $data['access_scope']),
                'status' => DocumentStatus::Active,
            ]);

            $this->capture($document, $data['content'], now());

            $this->audit->record('knowledge.document_created', $document, [
                'kind' => $kind->value, 'access_scope' => $document->access_scope->value,
            ], mallId: (string) $mall->getKey());

            return $document;
        });
    }

    /**
     * Changes a hand-written document. New text becomes a new revision; unchanged text does not.
     *
     * @param  array{title: string, source_kind: string, access_scope: string, content: string, source_reference?: string|null}  $data
     */
    public function revise(User $actor, KnowledgeDocument $document, array $data): KnowledgeDocument
    {
        $this->authorize($actor, $document->mall);
        $this->assertHandWritten($document);
        $kind = $this->writableKind($data['source_kind']);

        return DB::transaction(function () use ($document, $data, $kind): KnowledgeDocument {
            $document = KnowledgeDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            $document->fill([
                'source_kind' => $kind,
                'title' => trim($data['title']),
                'access_scope' => $this->scopeFor($kind, $data['access_scope']),
            ]);

            if (filled($data['source_reference'] ?? null)) {
                $document->source_locator = $this->locator($data['source_reference']);
            }

            $changed = array_keys($document->getDirty());
            $renamed = $document->isDirty('title');
            $document->save();

            $captured = $this->capture($document, $data['content'], now());

            if (! $captured && $renamed) {
                $this->refreshSearchText($document);
            }

            if ($captured || $changed !== []) {
                $this->audit->record('knowledge.document_revised', $document, [
                    'fields' => $changed, 'new_revision' => $captured, 'access_scope' => $document->access_scope->value,
                ], mallId: (string) $document->mall_id);
            }

            return $document;
        });
    }

    /**
     * Takes a hand-written document out of retrieval at once. Its revisions stay for the answers
     * that cited them.
     */
    public function withdraw(User $actor, KnowledgeDocument $document): void
    {
        $this->authorize($actor, $document->mall);
        $this->assertHandWritten($document);

        if ($document->status === DocumentStatus::Withdrawn) {
            return;
        }

        $document->update(['status' => DocumentStatus::Withdrawn]);
        $this->audit->record('knowledge.document_withdrawn', $document, mallId: (string) $document->mall_id);
    }

    public function restore(User $actor, KnowledgeDocument $document): void
    {
        $this->authorize($actor, $document->mall);
        $this->assertHandWritten($document);

        if ($document->status === DocumentStatus::Active) {
            return;
        }

        $document->update(['status' => DocumentStatus::Active]);
        $this->audit->record('knowledge.document_restored', $document, mallId: (string) $document->mall_id);
    }

    /**
     * Deletes a withdrawn hand-written document with every revision and chunk. Refused while an
     * answer in a stored conversation still cites it.
     */
    public function delete(User $actor, KnowledgeDocument $document): void
    {
        $this->authorize($actor, $document->mall);
        $this->assertHandWritten($document);

        if ($document->status !== DocumentStatus::Withdrawn) {
            throw new BusinessRuleViolation('knowledge.not_withdrawn', __('Withdraw the document before deleting it.'));
        }

        if ($this->isCited($document)) {
            throw new BusinessRuleViolation('knowledge.still_cited', __('An answer in a stored conversation still cites this document. It can be deleted when those conversations have expired.'));
        }

        DB::transaction(function () use ($document): void {
            $this->audit->record('knowledge.document_deleted', $document, ['title' => $document->title, 'kind' => $document->source_kind->value], mallId: (string) $document->mall_id);
            $document->delete();
        });
    }

    /**
     * One page of the mall's documents for its administrators, hand-written ones first.
     *
     * @param  array{kind?: string|null, status?: string|null, origin?: string|null, q?: string|null}  $filters
     * @return LengthAwarePaginator<int, KnowledgeDocument>
     */
    public function listForMall(User $actor, Mall $mall, array $filters = []): LengthAwarePaginator
    {
        $this->authorize($actor, $mall);

        return KnowledgeDocument::query()
            ->whereBelongsTo($mall)
            ->when(filled($filters['kind'] ?? null), fn ($query) => $query->where('source_kind', $filters['kind']))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['origin'] ?? null) === 'written', fn ($query) => $query->handWritten())
            ->when(($filters['origin'] ?? null) === 'captured', fn ($query) => $query->systemManaged())
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                // "!" is the LIKE escape character: a backslash one breaks PHP 8.3's PostgreSQL statement parser.
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower(trim((string) $filters['q']))).'%';
                $query->whereRaw("lower(title) like ? escape '!'", [$pattern]);
            })
            ->with('currentRevision')
            ->orderByRaw("case when source_locator like 'system:%' then 1 else 0 end")
            ->orderBy('title')
            ->orderBy('document_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Captures a document from the mall's own records (the sync calls this; no person acts). The
     * locator identifies the record, so calling it again with the same text changes nothing.
     *
     * @param  array{source_kind: SourceKind, source_locator: string, title: string, shop_id?: string|null, shop_product_id?: string|null}  $attributes
     * @return 'created'|'updated'|'unchanged'
     */
    public function storeSystemDocument(Mall $mall, array $attributes, string $content, ?KnowledgeDocument $existing = null): string
    {
        return DB::transaction(function () use ($mall, $attributes, $content, $existing): string {
            if ($existing === null) {
                $document = KnowledgeDocument::query()->create([
                    'mall_id' => $mall->getKey(),
                    'shop_id' => $attributes['shop_id'] ?? null,
                    'shop_product_id' => $attributes['shop_product_id'] ?? null,
                    'source_kind' => $attributes['source_kind'],
                    'title' => Str::limit($attributes['title'], 200, ''),
                    'source_locator' => $attributes['source_locator'],
                    'access_scope' => AccessScope::Public,
                    'status' => DocumentStatus::Active,
                ]);
                $this->capture($document, $content, now());

                return 'created';
            }

            $existing->fill(['title' => Str::limit($attributes['title'], 200, ''), 'status' => DocumentStatus::Active, 'access_scope' => AccessScope::Public]);
            $touched = $existing->isDirty();
            $renamed = $existing->isDirty('title');
            $existing->save();

            $captured = $this->capture($existing, $content, now());

            if (! $captured && $renamed) {
                $this->refreshSearchText($existing);
            }

            return $captured || $touched ? 'updated' : 'unchanged';
        });
    }

    /**
     * Takes a captured document out of retrieval because its record is no longer public.
     */
    public function withdrawSystemDocument(KnowledgeDocument $document): void
    {
        if ($document->status !== DocumentStatus::Withdrawn) {
            $document->update(['status' => DocumentStatus::Withdrawn]);
        }
    }

    /**
     * Stores the text as a new current revision with its chunks, unless it equals the current one.
     */
    private function capture(KnowledgeDocument $document, string $content, CarbonInterface $sourceUpdatedAt): bool
    {
        $parsed = $this->parser->parse($content);

        if ($parsed['sections'] === []) {
            throw new BusinessRuleViolation('knowledge.empty_content', __('Write the text the assistant may quote.'));
        }

        if (mb_strlen($parsed['text']) > (int) config('assistant.max_document_characters')) {
            throw new BusinessRuleViolation('knowledge.too_long', __('The text is too long. Split it into several documents of at most :max characters.', ['max' => (int) config('assistant.max_document_characters')]));
        }

        $hash = hash('sha256', $parsed['text']);
        $current = $document->current_revision_id === null ? null : DocumentRevision::query()->find($document->current_revision_id);

        if ($current !== null && $current->content_hash === $hash) {
            return false;
        }

        $revision = DocumentRevision::query()->create([
            'document_id' => $document->getKey(),
            'revision_no' => (int) DocumentRevision::query()->where('document_id', $document->getKey())->max('revision_no') + 1,
            'content_hash' => $hash,
            'content_text' => $parsed['text'],
            'source_updated_at' => $sourceUpdatedAt,
            'captured_at' => now(),
        ]);

        foreach ($this->chunker->chunk($parsed['sections'], (int) config('assistant.chunk_target'), (int) config('assistant.chunk_max')) as $number => $chunk) {
            KnowledgeChunk::query()->create([
                'revision_id' => $revision->getKey(),
                'chunk_no' => $number,
                'heading' => $chunk['heading'],
                'chunk_text' => $chunk['text'],
                'search_text' => $this->searchText($document->title, $chunk['heading'], $chunk['text']),
                'token_count' => count($this->analyzer->tokens($chunk['text'])),
            ]);
        }

        $document->forceFill(['current_revision_id' => $revision->getKey()])->save();

        return true;
    }

    /**
     * The title is part of what retrieval matches, so a renamed document re-reads it.
     */
    private function refreshSearchText(KnowledgeDocument $document): void
    {
        KnowledgeChunk::query()->where('revision_id', $document->current_revision_id)->get()->each(
            fn (KnowledgeChunk $chunk) => $chunk->update(['search_text' => $this->searchText($document->title, $chunk->heading, $chunk->chunk_text)])
        );
    }

    /**
     * The words of the title, the heading and the text, as retrieval compares them.
     */
    private function searchText(string $title, ?string $heading, string $text): string
    {
        return implode(' ', $this->analyzer->tokens($title.' '.($heading ?? '').' '.$text));
    }

    private function isCited(KnowledgeDocument $document): bool
    {
        return DB::table('message_citations')
            ->join('knowledge_chunks', 'knowledge_chunks.chunk_id', '=', 'message_citations.chunk_id')
            ->join('document_revisions', 'document_revisions.revision_id', '=', 'knowledge_chunks.revision_id')
            ->where('document_revisions.document_id', $document->getKey())
            ->exists();
    }

    private function writableKind(string $value): SourceKind
    {
        $kind = SourceKind::from($value);

        if (! in_array($kind, SourceKind::writable(), true)) {
            throw new BusinessRuleViolation('knowledge.kind_not_writable', __('That kind of document is captured from the mall records and cannot be written by hand.'));
        }

        return $kind;
    }

    private function scopeFor(SourceKind $kind, string $requested): AccessScope
    {
        return $kind->isAlwaysRestricted() ? AccessScope::MallAdmin : AccessScope::from($requested);
    }

    private function locator(?string $reference): string
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            return 'manual:'.Str::uuid();
        }

        if (str_starts_with(Str::lower($reference), KnowledgeDocument::SYSTEM_PREFIX)) {
            throw new BusinessRuleViolation('knowledge.reserved_reference', __('A source reference cannot start with "system:".'));
        }

        return $reference;
    }

    private function assertHandWritten(KnowledgeDocument $document): void
    {
        if ($document->isSystemManaged()) {
            throw new BusinessRuleViolation('knowledge.system_managed', __('This document is captured from the mall records. Change the shop, product or facility instead.'));
        }
    }

    private function authorize(User $actor, Mall $mall): void
    {
        Gate::forUser($actor)->authorize(Ability::AdministerMall->value, $mall);
    }
}
