<?php

namespace Tests\Feature\Modules\ChatbotRag\Services;

use App\Modules\ChatbotRag\DTOs\RetrievedChunkData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\SourceKind;
use App\Modules\ChatbotRag\Models\KnowledgeChunk;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeService;
use App\Modules\ChatbotRag\Services\Retrieval\KnowledgeRetriever;
use App\Modules\ChatbotRag\Services\Retrieval\PostgresChunkSearch;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\ChatbotRag\CreatesKnowledge;
use Tests\TestCase;

/**
 * Runs against the search of the connected database: the portable scan on SQLite, full-text and
 * trigram search on PostgreSQL (composer test:pgsql).
 */
class KnowledgeRetrieverTest extends TestCase
{
    use CreatesKnowledge, RefreshDatabase;

    private const FAQ = "Q: What does parking cost?\nA: Parking costs BDT 30 for the first two hours.\n\nQ: Where is the toilet?\nA: Toilets are on every floor next to Lift A.\n\nQ: Is there a prayer room?\nA: The prayer room is on the first floor near Book Nook.";

    private Mall $mall;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mall = Mall::factory()->create();
    }

    /**
     * @return list<RetrievedChunkData>
     */
    private function retrieve(string $question, AccessScope $reader = AccessScope::Public, ?Mall $mall = null): array
    {
        return app(KnowledgeRetriever::class)->retrieve($mall ?? $this->mall, $reader, $question);
    }

    public function test_the_chunk_that_answers_comes_first_with_the_source_it_is_attributed_to(): void
    {
        $this->travelTo('2026-11-15 10:00:00');
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $best = $this->retrieve('How much does parking cost?')[0];

        $this->assertSame('Parking costs BDT 30 for the first two hours.', $best->text);
        $this->assertSame('What does parking cost?', $best->heading);
        $this->assertSame('Visitor FAQ', $best->title);
        $this->assertSame($document->getKey(), $best->documentId);
        $this->assertSame(SourceKind::Faq, $best->kind);
        $this->assertSame(AccessScope::Public, $best->scope);
        $this->assertSame($document->source_locator, $best->locator);
        $this->assertSame(1, $best->revisionNo);
        $this->assertSame('2026-11-15 10:00:00', $best->capturedAt->format('Y-m-d H:i:s'));
        $this->assertSame(1.0, $best->coverage);
    }

    public function test_a_word_with_the_same_meaning_finds_the_text(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $this->assertSame('Toilets are on every floor next to Lift A.', $this->retrieve('washroom?')[0]->text);
    }

    public function test_a_misspelt_word_still_finds_the_text(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $found = $this->retrieve('parkng cost');

        $this->assertSame('Parking costs BDT 30 for the first two hours.', $found[0]->text);
        $this->assertGreaterThanOrEqual(0.6, $found[0]->coverage);
    }

    public function test_a_bangla_question_finds_english_text_through_the_word_list(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $this->assertSame('The prayer room is on the first floor near Book Nook.', $this->retrieve('নামাজের জায়গা কোথায়?')[0]->text);
    }

    public function test_bangla_text_is_found_by_a_bangla_question(): void
    {
        $this->knowledge($this->mall, 'প্রশ্নোত্তর', "প্রশ্ন: হারানো জিনিস কোথায় পাব?\nউত্তর: হারানো জিনিস তথ্য কেন্দ্রে জমা থাকে।");

        $this->assertSame('হারানো জিনিস তথ্য কেন্দ্রে জমা থাকে।', $this->retrieve('হারানো জিনিস')[0]->text);
    }

    public function test_coverage_is_the_share_of_the_questions_words_the_chunk_contains(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $best = $this->retrieve('parking for helicopters')[0];

        $this->assertSame('Parking costs BDT 30 for the first two hours.', $best->text);
        $this->assertSame(0.5, $best->coverage);
    }

    public function test_a_question_about_something_no_document_mentions_retrieves_nothing(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $this->assertSame([], $this->retrieve('helicopter landing permit'));
        $this->assertSame([], $this->retrieve('?? the of'));
    }

    public function test_a_document_of_another_mall_is_never_retrieved(): void
    {
        $this->knowledge(Mall::factory()->create(), 'Other mall FAQ', self::FAQ);

        $this->assertSame([], $this->retrieve('parking cost'));
    }

    public function test_a_withdrawn_document_is_not_retrieved_until_it_is_restored(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);
        $admin = $this->administratorOf($this->mall);

        app(KnowledgeService::class)->withdraw($admin, $document);
        $this->assertSame([], $this->retrieve('parking cost'));

        app(KnowledgeService::class)->restore($admin, $document);
        $this->assertCount(1, $this->retrieve('parking cost'));
    }

    public function test_a_restricted_document_is_retrieved_for_administrators_and_never_for_the_public(): void
    {
        $this->knowledge($this->mall, 'Security handbook', 'The security control room code word is BLUE HERON.', scope: 'mall_admin', kind: 'admin_report');

        $this->assertSame([], $this->retrieve('security control room code word'));
        $this->assertSame('The security control room code word is BLUE HERON.', $this->retrieve('security control room code word', AccessScope::MallAdmin)[0]->text);
    }

    public function test_administrators_of_one_mall_do_not_retrieve_another_malls_restricted_document(): void
    {
        $this->knowledge(Mall::factory()->create(), 'Security handbook', 'The security control room code word is BLUE HERON.', scope: 'mall_admin', kind: 'admin_report');

        $this->assertSame([], $this->retrieve('security control room code word', AccessScope::MallAdmin));
    }

    public function test_only_the_current_revision_is_retrieved_after_the_text_changed(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', "Q: What does parking cost?\nA: Parking costs BDT 30.");

        app(KnowledgeService::class)->revise($this->administratorOf($this->mall), $document, ['title' => 'Visitor FAQ', 'source_kind' => 'faq', 'access_scope' => 'public', 'content' => "Q: What does parking cost?\nA: Parking costs BDT 45."]);

        $found = $this->retrieve('parking cost');
        $this->assertSame(['Parking costs BDT 45.'], array_map(fn (RetrievedChunkData $chunk): string => $chunk->text, $found));
        $this->assertSame(2, $found[0]->revisionNo);
        $this->assertSame(2, KnowledgeChunk::query()->count());
    }

    public function test_a_document_made_restricted_stops_being_retrieved_for_the_public_at_once(): void
    {
        $document = $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        app(KnowledgeService::class)->revise($this->administratorOf($this->mall), $document, ['title' => 'Visitor FAQ', 'source_kind' => 'faq', 'access_scope' => 'mall_admin', 'content' => self::FAQ]);

        $this->assertSame([], $this->retrieve('parking cost'));
    }

    public function test_retrieval_can_be_limited_to_kinds_of_source(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', "Q: Parking?\nA: Parking is in the basement.");
        $this->knowledge($this->mall, 'Parking policy', 'Parking overnight is not allowed.', kind: 'mall_guide');

        $found = app(KnowledgeRetriever::class)->retrieve($this->mall, AccessScope::Public, 'parking', kinds: [SourceKind::MallGuide]);

        $this->assertSame(['Parking overnight is not allowed.'], array_map(fn (RetrievedChunkData $chunk): string => $chunk->text, $found));
    }

    public function test_a_question_made_of_search_operators_is_treated_as_plain_words(): void
    {
        $this->knowledge($this->mall, 'Visitor FAQ', self::FAQ);

        $found = $this->retrieve("parking' | !cost & (x) <-> %_; DROP TABLE knowledge_chunks --");

        $this->assertSame('Parking costs BDT 30 for the first two hours.', $found[0]->text);
        $this->assertSame(3, KnowledgeChunk::query()->count());
    }

    public function test_postgresql_search_uses_stemming_and_trigram_similarity(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Full-text and trigram search exist on PostgreSQL only; run composer test:pgsql.');
        }

        $this->knowledge($this->mall, 'Opening times', 'The mall opens at 10:00 and the cinema is closing at midnight.', kind: 'mall_guide');
        $chunkId = KnowledgeChunk::query()->sole()->getKey();
        $search = app(PostgresChunkSearch::class);
        $mallId = (string) $this->mall->getKey();

        $this->assertArrayHasKey($chunkId, $search->search($mallId, ['public'], ['opening'], null, 10), 'Stemming: "opening" must match "opens".');
        $this->assertArrayHasKey($chunkId, $search->search($mallId, ['public'], ['cinma'], null, 10), 'Trigram: "cinma" must match "cinema".');
        $this->assertSame([], $search->search($mallId, ['mall_admin'], ['opening'], null, 10));
        $this->assertSame([], $search->search($mallId, ['public'], ['helicopter'], null, 10));
        $this->assertSame(1, DB::table('pg_indexes')->where('indexname', 'knowledge_chunks_search_vector_index')->count());
        $this->assertSame(1, DB::table('pg_indexes')->where('indexname', 'knowledge_chunks_search_text_trgm_index')->count());
    }
}
