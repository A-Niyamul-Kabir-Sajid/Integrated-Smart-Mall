<?php

namespace Tests\Feature\Seeders;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Models\AssistantInteraction;
use App\Modules\ChatbotRag\Models\ChatMessage;
use App\Modules\ChatbotRag\Models\Conversation;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Evaluation\DemoEvaluationSet;
use App\Modules\ChatbotRag\Services\Evaluation\EvaluationRunner;
use App\Modules\MallShopDirectory\Models\Mall;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoKnowledgeSeeder;
use Database\Seeders\DemoMallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demonstration knowledge and the evaluation questions written against it. This is the
 * assistant's evaluation run as a test: when a change makes an answer wrong, a case here fails.
 */
class DemoKnowledgeSeederTest extends TestCase
{
    use RefreshDatabase;

    private Mall $mall;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-16 06:00:00');
        $this->seed([DemoMallSeeder::class, DemoAccountsSeeder::class, DemoKnowledgeSeeder::class]);
        $this->mall = Mall::query()->where('slug', DemoMallSeeder::MALL_SLUG)->sole();
    }

    public function test_seeds_three_written_documents_and_captures_the_public_mall_records(): void
    {
        $written = KnowledgeDocument::query()->handWritten()->orderBy('title')->get();

        $this->assertSame([DemoEvaluationSet::RULES, DemoEvaluationSet::HANDBOOK, DemoEvaluationSet::FAQ], $written->pluck('title')->all());
        $this->assertSame(['public', 'mall_admin', 'public'], $written->map(fn (KnowledgeDocument $document): string => $document->access_scope->value)->all());
        $this->assertSame(1, KnowledgeDocument::query()->where('source_locator', 'system:floors')->count());
        $this->assertSame(13, KnowledgeDocument::query()->where('source_kind', 'shop_profile')->count(), 'One profile per listed shop; the draft shop has none.');
        $this->assertGreaterThan(20, KnowledgeDocument::query()->where('source_kind', 'product_listing')->count());
        $this->assertSame(0, KnowledgeDocument::query()->systemManaged()->where('access_scope', '!=', 'public')->count());
    }

    public function test_every_evaluation_case_passes_on_the_demonstration_data(): void
    {
        $administrator = User::query()->where('email', 'mall.admin@demo.test')->sole();

        $result = app(EvaluationRunner::class)->run($this->mall, DemoEvaluationSet::cases(), $administrator);

        $failed = array_values(array_filter($result['cases'], fn (array $case): bool => $case['status'] !== 'passed'));
        $this->assertSame([], array_map(fn (array $case): string => $case['id'].' ['.$case['outcome'].']: '.implode(' ', $case['problems']), $failed));
        $this->assertSame(count(DemoEvaluationSet::cases()), $result['summary']['passed']);
        $this->assertSame(0, $result['summary']['unsupported_citations']);
        $this->assertSame(0, $result['summary']['fabricated_offers']);
        $this->assertSame(0, $result['summary']['access_violations']);
        $this->assertSame(1.0, $result['summary']['retrieval']['hit_at_3']);
        $this->assertGreaterThanOrEqual(0.9, $result['summary']['retrieval']['hit_at_1']);
    }

    public function test_the_evaluation_stores_no_conversation_and_no_interaction(): void
    {
        app(EvaluationRunner::class)->run($this->mall, DemoEvaluationSet::cases(), User::query()->where('email', 'mall.admin@demo.test')->sole());

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, ChatMessage::query()->count());
        $this->assertSame(0, AssistantInteraction::query()->count());
    }

    public function test_without_an_administrator_the_administrators_cases_are_skipped_not_failed(): void
    {
        $result = app(EvaluationRunner::class)->run($this->mall, DemoEvaluationSet::cases());

        $this->assertSame(7, $result['summary']['skipped']);
        $this->assertSame(0, $result['summary']['failed']);
        $this->assertSame(['passed' => 0, 'failed' => 0, 'skipped' => 7], $result['summary']['groups']['admin']);
    }

    public function test_the_command_prints_the_result_and_succeeds(): void
    {
        $this->artisan('assistant:evaluate')
            ->expectsOutputToContain('Cases: '.count(DemoEvaluationSet::cases()).', passed '.count(DemoEvaluationSet::cases()).', failed 0, skipped 0.')
            ->expectsOutputToContain('Quotes not found in their source: 0. Offers not in the catalogue: 0. Sources shown to the wrong reader: 0.')
            ->assertSuccessful();

        $this->artisan('assistant:evaluate', ['mall' => 'no-such-mall'])->assertFailed();
    }
}
