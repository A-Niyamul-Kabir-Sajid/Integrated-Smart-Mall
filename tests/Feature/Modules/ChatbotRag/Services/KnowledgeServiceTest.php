<?php

namespace Tests\Feature\Modules\ChatbotRag\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\DocumentStatus;
use App\Modules\ChatbotRag\Enums\SourceKind;
use App\Modules\ChatbotRag\Models\DocumentRevision;
use App\Modules\ChatbotRag\Models\KnowledgeChunk;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Feature\Modules\ChatbotRag\CreatesKnowledge;
use Tests\TestCase;

class KnowledgeServiceTest extends TestCase
{
    use CreatesKnowledge, RefreshDatabase;

    private const FAQ = "Q: Is there free Wi-Fi?\nA: Yes. Join the network NCM-Guest.\n\nQ: What does parking cost?\nA: BDT 30 for the first two hours.";

    private Mall $mall;

    private KnowledgeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
        $this->service = app(KnowledgeService::class);
    }

    public function test_creating_a_document_captures_revision_one_cut_into_chunks_with_their_metadata(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $revision = DocumentRevision::query()->sole();
        $this->assertSame($revision->getKey(), $document->current_revision_id);
        $this->assertSame(1, $revision->revision_no);
        $this->assertSame(hash('sha256', self::FAQ), $revision->content_hash);
        $this->assertSame(self::FAQ, $revision->content_text);
        $this->assertSame('2026-11-15 10:00:00', $revision->captured_at->format('Y-m-d H:i:s'));

        $chunks = KnowledgeChunk::query()->orderBy('chunk_no')->get();
        $this->assertSame([0, 1], $chunks->pluck('chunk_no')->all());
        $this->assertSame(['Is there free Wi-Fi?', 'What does parking cost?'], $chunks->pluck('heading')->all());
        $this->assertSame('Yes. Join the network NCM-Guest.', $chunks[0]->chunk_text);
        $this->assertSame('visitor faq is there free wi fi wifi yes join the network ncm guest ncmguest', $chunks[0]->search_text);
        $this->assertSame(7, $chunks[1]->token_count);
        $this->assertSame(SourceKind::Faq, $document->source_kind);
        $this->assertSame(AccessScope::Public, $document->access_scope);
        $this->assertStringStartsWith('manual:', $document->source_locator);
    }

    public function test_creating_a_document_is_audited_for_the_mall(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.document_created', 'subject_id' => $document->getKey(), 'mall_id' => $this->mall->getKey()]);
    }

    public function test_saving_unchanged_text_captures_no_revision(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $this->service->revise($this->administratorOf($this->mall), $document, ['title' => 'Visitor FAQ', 'source_kind' => 'faq', 'access_scope' => 'public', 'content' => self::FAQ."\r\n\r\n"]);

        $this->assertSame(1, DocumentRevision::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'like', 'knowledge.%')->count());
    }

    public function test_changed_text_becomes_the_current_revision_and_the_old_one_is_kept(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);
        $first = $document->current_revision_id;

        $revised = $this->service->revise($this->administratorOf($this->mall), $document, ['title' => 'Visitor FAQ', 'source_kind' => 'faq', 'access_scope' => 'public', 'content' => "Q: What does parking cost?\nA: BDT 40 per visit."]);

        $current = DocumentRevision::query()->findOrFail($revised->current_revision_id);
        $this->assertNotSame($first, $current->getKey());
        $this->assertSame(2, $current->revision_no);
        $this->assertSame(['BDT 40 per visit.'], $current->chunks->pluck('chunk_text')->all());
        $this->assertSame(2, KnowledgeChunk::query()->where('revision_id', $first)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.document_revised', 'subject_id' => $document->getKey()]);
    }

    public function test_renaming_a_document_refreshes_what_retrieval_matches_without_a_new_revision(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', "Q: Pets?\nA: Not allowed.");

        $this->service->revise($this->administratorOf($this->mall), $document, ['title' => 'House rules', 'source_kind' => 'mall_guide', 'access_scope' => 'public', 'content' => "Q: Pets?\nA: Not allowed."]);

        $this->assertSame(1, DocumentRevision::query()->count());
        $this->assertSame('house rules pets not allowed', KnowledgeChunk::query()->sole()->search_text);
        $this->assertSame(SourceKind::MallGuide, $document->refresh()->source_kind);
    }

    public function test_a_revision_cannot_be_edited_in_place(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $this->expectException(LogicException::class);

        DocumentRevision::query()->sole()->update(['content_text' => 'Changed behind the back']);
    }

    public function test_an_internal_note_is_restricted_even_when_public_is_requested(): void
    {
        $document = $this->knowledge($this->mall, 'Security rota', 'Guards change at 14:00.', scope: 'public', kind: 'admin_report');

        $this->assertSame(AccessScope::MallAdmin, $document->access_scope);
    }

    public function test_text_without_anything_to_quote_is_refused_and_stores_nothing(): void
    {
        try {
            $this->knowledge($this->mall, 'Empty', "# Only a heading\n\n");
            $this->fail('Empty text was accepted.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('knowledge.empty_content', $violation->rule);
        }

        $this->assertSame(0, KnowledgeDocument::query()->count());
        $this->assertSame(0, DocumentRevision::query()->count());
    }

    public function test_text_longer_than_the_limit_is_refused(): void
    {
        config(['assistant.max_document_characters' => 50]);

        try {
            $this->knowledge($this->mall, 'Long', str_repeat('word ', 20));
            $this->fail('Text over the limit was accepted.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('knowledge.too_long', $violation->rule);
        }

        $this->assertSame(0, KnowledgeDocument::query()->count());
    }

    public function test_kinds_captured_from_mall_records_cannot_be_written_by_hand(): void
    {
        try {
            $this->knowledge($this->mall, 'Fake shop', 'A shop that does not exist.', kind: 'shop_profile');
            $this->fail('A shop profile was written by hand.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('knowledge.kind_not_writable', $violation->rule);
        }

        $this->assertSame(0, KnowledgeDocument::query()->count());
    }

    public function test_a_source_reference_cannot_claim_to_be_a_mall_record(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        $this->service->create($this->administratorOf($this->mall), $this->mall, ['title' => 'Spoof', 'source_kind' => 'faq', 'access_scope' => 'public', 'content' => 'Text.', 'source_reference' => 'System:floors']);
    }

    public function test_a_document_captured_from_mall_records_cannot_be_changed_by_hand(): void
    {
        $this->service->storeSystemDocument($this->mall, ['source_kind' => SourceKind::MallGuide, 'source_locator' => 'system:floors', 'title' => 'Floors'], 'Two floors.');
        $document = KnowledgeDocument::query()->sole();
        $admin = $this->administratorOf($this->mall);

        foreach ([
            fn () => $this->service->revise($admin, $document, ['title' => 'Mine', 'source_kind' => 'faq', 'access_scope' => 'public', 'content' => 'Three floors.']),
            fn () => $this->service->withdraw($admin, $document),
            fn () => $this->service->delete($admin, $document),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A captured document was changed by hand.');
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('knowledge.system_managed', $violation->rule);
            }
        }

        $this->assertSame('Floors', $document->refresh()->title);
        $this->assertSame(DocumentStatus::Active, $document->status);
    }

    public function test_a_withdrawn_document_can_be_restored_and_both_are_audited(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);
        $admin = $this->administratorOf($this->mall);

        $this->service->withdraw($admin, $document);
        $this->assertSame(DocumentStatus::Withdrawn, $document->refresh()->status);

        $this->service->restore($admin, $document);
        $this->assertSame(DocumentStatus::Active, $document->refresh()->status);

        $this->assertSame(1, AuditLog::query()->where('action', 'knowledge.document_withdrawn')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'knowledge.document_restored')->count());
    }

    public function test_an_active_document_cannot_be_deleted(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        try {
            $this->service->delete($this->administratorOf($this->mall), $document);
            $this->fail('An active document was deleted.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('knowledge.not_withdrawn', $violation->rule);
        }

        $this->assertModelExists($document);
    }

    public function test_deleting_a_withdrawn_document_removes_its_revisions_and_chunks(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);
        $admin = $this->administratorOf($this->mall);
        $this->service->withdraw($admin, $document);

        $this->service->delete($admin, $document);

        $this->assertModelMissing($document);
        $this->assertSame(0, DocumentRevision::query()->count());
        $this->assertSame(0, KnowledgeChunk::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.document_deleted', 'mall_id' => $this->mall->getKey()]);
    }

    public function test_a_document_an_answer_still_cites_cannot_be_deleted(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);
        $admin = $this->administratorOf($this->mall);
        $this->service->withdraw($admin, $document);
        $conversation = (string) Str::uuid();
        $message = (string) Str::uuid();
        DB::table('conversations')->insert(['conversation_id' => $conversation, 'mall_id' => $this->mall->getKey(), 'access_scope' => 'public', 'created_at' => now(), 'expires_at' => now()->addDay()]);
        DB::table('chat_messages')->insert(['message_id' => $message, 'conversation_id' => $conversation, 'sequence_no' => 0, 'role' => 'assistant', 'content' => 'Yes.', 'created_at' => now()]);
        DB::table('message_citations')->insert(['message_id' => $message, 'chunk_id' => KnowledgeChunk::query()->orderBy('chunk_no')->first()->getKey(), 'citation_no' => 1]);

        try {
            $this->service->delete($admin, $document);
            $this->fail('A cited document was deleted.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('knowledge.still_cited', $violation->rule);
        }

        $this->assertModelExists($document);
    }

    public function test_someone_who_does_not_administer_the_mall_cannot_write_its_knowledge(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->service->create(User::factory()->create(), $this->mall, ['title' => 'Sneaky', 'source_kind' => 'faq', 'access_scope' => 'public', 'content' => 'Text.']);
    }

    public function test_an_administrator_of_another_mall_cannot_change_this_malls_document(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $this->expectException(AuthorizationException::class);

        $this->service->withdraw($this->administratorOf(Mall::factory()->create()), $document);
    }
}
