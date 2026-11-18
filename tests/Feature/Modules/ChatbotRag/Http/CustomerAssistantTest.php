<?php

namespace Tests\Feature\Modules\ChatbotRag\Http;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Exceptions\GenerationUnavailable;
use App\Modules\ChatbotRag\Models\ChatMessage;
use App\Modules\ChatbotRag\Models\Conversation;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Models\MessageCitation;
use App\Modules\ChatbotRag\Models\MessageRecommendation;
use App\Modules\ChatbotRag\Services\Assistant\ConversationService;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Modules\ChatbotRag\BuildsAssistantMall;
use Tests\Fixtures\Assistant\FakeAnswerGenerator;
use Tests\TestCase;

class CustomerAssistantTest extends TestCase
{
    use BuildsAssistantMall, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->buildMall();
    }

    private function page(): string
    {
        return route('assistant.show', ['publicMall' => 'harbour-mall']);
    }

    private function send(string $message)
    {
        return $this->post(route('assistant.messages.store', ['publicMall' => 'harbour-mall']), ['message' => $message]);
    }

    public function test_a_guest_opens_the_assistant_and_sees_example_questions(): void
    {
        $this->get($this->page())
            ->assertOk()
            ->assertSee('Ask the assistant')
            ->assertSee('Where can I get a cold drink?')
            ->assertSee('<label for="assistant-message"', false)
            ->assertSee('A conversation is kept for 1 day and you can delete it at any time.')
            ->assertDontSee('Delete this conversation')
            ->assertDontSee('Google Gemini');

        $this->assertSame(0, Conversation::query()->count(), 'Opening the page starts no conversation.');
    }

    public function test_a_guest_asks_a_question_and_reads_the_answer_with_its_source(): void
    {
        $this->send('What does parking cost?')->assertRedirect($this->page().'#latest');

        $this->get($this->page())
            ->assertOk()
            ->assertSeeInOrder(['What does parking cost?', 'Parking costs BDT 30 for the first two hours and BDT 20 for every hour after that. [1]', 'Sources', '[1] Visitor FAQ', 'What does parking cost?', 'Frequently asked questions', 'Quoted from the sources'])
            ->assertSee('id="latest"', false)
            ->assertSee('Delete this conversation');

        $conversation = Conversation::query()->sole();
        $this->assertNull($conversation->user_id);
        $this->assertSame(AccessScope::Public, $conversation->access_scope);
        $this->assertSame('2026-11-17 06:00:00', $conversation->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(['user', 'assistant'], ChatMessage::query()->orderBy('sequence_no')->get()->map(fn (ChatMessage $message): string => $message->role->value)->all());
        $this->assertSame([0, 1], ChatMessage::query()->orderBy('sequence_no')->pluck('sequence_no')->all());
        $this->assertSame(1, MessageCitation::query()->count());
    }

    public function test_a_conversation_continues_and_keeps_its_order(): void
    {
        $this->send('What does parking cost?');
        $this->send('Is there free Wi-Fi?');

        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame([0, 1, 2, 3], ChatMessage::query()->orderBy('sequence_no')->pluck('sequence_no')->all());
        $this->get($this->page())->assertSeeInOrder(['What does parking cost?', 'Parking costs BDT 30', 'Is there free Wi-Fi?', 'Connect to the network HM-Guest']);
    }

    public function test_a_question_nothing_answers_shows_no_guess_and_directions_to_the_information_desk(): void
    {
        $this->send('Can I land a helicopter on the roof?');

        $this->get($this->page())
            ->assertSee('I could not find that in the mall&#039;s information, so I will not guess.', false)
            ->assertSee('Directions to Information Desk')
            ->assertSee(route('assistant.go', ['publicMall' => 'harbour-mall', 'type' => 'facility', 'id' => $this->desk->getKey()]), false)
            ->assertDontSee('Sources (');
    }

    public function test_recommended_offers_show_the_price_and_stock_of_the_moment_the_page_is_opened(): void
    {
        $this->send('cheapest cola');
        $this->assertSame([$this->colaAtMart->getKey()], MessageRecommendation::query()->pluck('shop_product_id')->all());
        $this->get($this->page())->assertSeeInOrder(['Cola Soft Drink', 'Fresh Mart', 'BDT 40.00', 'Available', 'Directions to Fresh Mart']);

        $this->colaAtMart->update(['price_amount' => '45.00', 'availability_status' => 'unavailable']);
        $this->get($this->page())->assertSeeInOrder(['Offers from the catalogue', 'BDT 45.00', 'Currently unavailable']);

        $this->colaAtMart->update(['status' => 'archived']);
        $this->get($this->page())->assertSee('This offer is no longer on sale.')->assertDontSee('BDT 45.00');
    }

    public function test_a_source_that_was_withdrawn_or_changed_is_marked_when_the_conversation_is_shown_again(): void
    {
        $this->send('What does parking cost?');
        $document = KnowledgeDocument::query()->where('title', 'Visitor FAQ')->sole();
        $admin = $this->administratorOf($this->mall);

        app(KnowledgeService::class)->revise($admin, $document, ['title' => 'Visitor FAQ', 'source_kind' => 'faq', 'access_scope' => 'public', 'content' => "Q: What does parking cost?\nA: Parking costs BDT 60."]);
        $this->get($this->page())->assertSee('From an earlier version of this document.')->assertSee('[1] Visitor FAQ');

        app(KnowledgeService::class)->withdraw($admin, $document);
        $this->get($this->page())->assertSee('This source has since been withdrawn.');

        app(KnowledgeService::class)->restore($admin, $document);
        app(KnowledgeService::class)->revise($admin, $document, ['title' => 'Visitor FAQ', 'source_kind' => 'faq', 'access_scope' => 'mall_admin', 'content' => "Q: What does parking cost?\nA: Parking costs BDT 60."]);
        $this->get($this->page())->assertSee('This source is no longer available.')->assertDontSee('[1] Visitor FAQ');
    }

    public function test_a_signed_in_visitor_keeps_the_conversation_across_sign_ins_and_nobody_else_sees_it(): void
    {
        $visitor = User::factory()->create();
        $this->actingAs($visitor)->send('What does parking cost?');

        $conversation = Conversation::query()->sole();
        $this->assertSame($visitor->getKey(), $conversation->user_id);
        $this->assertSame('2026-12-16 06:00:00', $conversation->expires_at->format('Y-m-d H:i:s'));

        $this->flushSession();
        $this->actingAs($visitor)->get($this->page())->assertSee('Parking costs BDT 30');

        $this->flushSession();
        $this->actingAs(User::factory()->create())->get($this->page())->assertDontSee('Parking costs BDT 30')->assertSee('Try asking');
    }

    public function test_a_guest_conversation_belongs_to_its_session_and_an_id_alone_opens_nothing(): void
    {
        $this->send('What does parking cost?');
        $conversation = Conversation::query()->sole();

        $this->flushSession();
        $this->get($this->page())->assertDontSee('Parking costs BDT 30');

        $this->withSession(['assistant.conversations.public.'.Mall::factory()->create()->getKey() => $conversation->getKey()])
            ->get($this->page())->assertDontSee('Parking costs BDT 30');
        $this->get($this->page().'?conversation='.$conversation->getKey())->assertDontSee('Parking costs BDT 30');
    }

    public function test_a_session_cannot_open_another_accounts_conversation_by_holding_its_id(): void
    {
        $owner = User::factory()->create();
        $theirs = Conversation::factory()->for($this->mall)->forUser($owner)->create();
        app(ConversationService::class)->record($theirs, $this->ask('What does parking cost?'));
        $key = 'assistant.conversations.public.'.$this->mall->getKey();

        $this->withSession([$key => $theirs->getKey()])->get($this->page())->assertDontSee('Parking costs BDT 30');
        $this->actingAs(User::factory()->create())->withSession([$key => $theirs->getKey()])->get($this->page())->assertDontSee('Parking costs BDT 30');
        $this->actingAs($owner)->get($this->page())->assertSee('Parking costs BDT 30');
    }

    public function test_a_conversation_of_another_mall_is_never_shown(): void
    {
        $this->send('What does parking cost?');
        $other = Mall::factory()->create(['slug' => 'other-mall']);

        $this->get(route('assistant.show', ['publicMall' => 'other-mall']))->assertOk()->assertDontSee('Parking costs BDT 30');
        $this->assertSame(0, Conversation::query()->whereBelongsTo($other)->count());
    }

    public function test_a_visitor_deletes_the_conversation_with_everything_in_it(): void
    {
        $this->send('cheapest cola');
        $this->send('What does parking cost?');

        $this->delete(route('assistant.destroy', ['publicMall' => 'harbour-mall']))
            ->assertRedirect($this->page())
            ->assertSessionHas('status', 'The conversation was deleted.');

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, ChatMessage::query()->count());
        $this->assertSame(0, MessageCitation::query()->count());
        $this->assertSame(0, MessageRecommendation::query()->count());
        $this->get($this->page())->assertSee('Try asking');
    }

    public function test_an_expired_conversation_is_not_shown_and_a_new_one_starts(): void
    {
        $this->send('What does parking cost?');

        $this->travel(25)->hours();

        $this->get($this->page())->assertDontSee('Parking costs BDT 30');
        $this->send('Is there free Wi-Fi?');
        $this->assertSame(2, Conversation::query()->count());
        $this->assertSame(1, app(ConversationService::class)->purgeExpired());
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_an_empty_or_too_long_question_is_refused_with_a_message_and_nothing_is_stored(): void
    {
        $this->from($this->page())->send('   ')->assertRedirect($this->page())->assertSessionHasErrors(['message' => 'Type a question first.']);
        $this->from($this->page())->send(str_repeat('a', 501))->assertSessionHasErrors(['message' => 'Keep the question under 500 characters.']);
        $this->from($this->page())->post(route('assistant.messages.store', ['publicMall' => 'harbour-mall']), ['message' => ['an', 'array']])->assertSessionHasErrors('message');

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_what_the_visitor_typed_is_escaped_on_the_page(): void
    {
        $this->send('<script>alert("x")</script> parking cost?');

        $this->get($this->page())
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; parking cost?', false)
            ->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_personal_details_are_not_stored_with_the_question(): void
    {
        $this->send('What does parking cost? rahim@example.com 01712345678');

        $stored = ChatMessage::query()->orderBy('sequence_no')->first()->content;
        $this->assertSame('What does parking cost? [email removed] [number removed]', $stored);
        $this->assertDatabaseMissing('chat_messages', ['content' => 'What does parking cost? rahim@example.com 01712345678']);
    }

    public function test_questions_are_limited_to_twenty_a_minute_per_visitor(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->send('hi')->assertRedirect();
        }

        $this->send('hi')->assertTooManyRequests();
        $this->assertSame(40, ChatMessage::query()->count());
    }

    public function test_an_unknown_draft_or_archived_mall_has_no_assistant(): void
    {
        Mall::factory()->draft()->create(['slug' => 'draft-mall']);

        $this->get(route('assistant.show', ['publicMall' => 'draft-mall']))->assertNotFound();
        $this->post(route('assistant.messages.store', ['publicMall' => 'no-such-mall']), ['message' => 'hi'])->assertNotFound();
    }

    public function test_the_header_and_the_mall_page_link_to_the_assistant(): void
    {
        $this->get(route('malls.show', ['publicMall' => 'harbour-mall']))
            ->assertSee('href="'.$this->page().'"', false)
            ->assertSee('Ask the assistant');
    }

    public function test_a_model_writes_the_answer_from_the_public_sources_and_shops_it_names_get_directions(): void
    {
        $this->captureMallRecords();
        $model = $this->useModel(FakeAnswerGenerator::answering('Cafe Brew sells coffee and fresh pastries on the Ground Floor, unit G-01.'));

        $this->send('Tell me about the coffee shop Cafe Brew');

        $request = $model->requests[0];
        $this->assertSame('Tell me about the coffee shop Cafe Brew', $request->question);
        $this->assertSame('en', $request->language);
        $this->assertSame('Harbour Mall', $request->mallName);
        $this->assertNotEmpty($request->sources);
        foreach ($request->sources as $source) {
            $this->assertTrue($source->isPublic());
        }

        $answer = ChatMessage::query()->orderByDesc('sequence_no')->first();
        $this->assertSame('Cafe Brew sells coffee and fresh pastries on the Ground Floor, unit G-01.', $answer->content);
        $this->assertSame(FakeAnswerGenerator::MODEL, $answer->model_label);
        $this->assertSame('gemini', $answer->meta['driver']);
        $this->assertContains('Cafe Brew', array_column($answer->meta['handoffs'], 'label'));
        $this->get($this->page())
            ->assertSee('Worded by an AI model from the sources')
            ->assertSee('Directions to Cafe Brew')
            ->assertSee(route('assistant.go', ['publicMall' => 'harbour-mall', 'type' => 'shop', 'id' => $this->cafe->getKey()]), false);
    }

    public function test_a_shop_named_only_in_the_models_text_still_gets_a_directions_link(): void
    {
        $this->useModel(FakeAnswerGenerator::answering('Parking costs BDT 30 for the first two hours. Book Nook validates parking tickets.'));

        $this->send('What does parking cost?');

        $handoffs = ChatMessage::query()->orderByDesc('sequence_no')->first()->meta['handoffs'];
        $this->assertSame([['type' => 'shop', 'id' => $this->books->getKey(), 'label' => 'Book Nook', 'detail' => 'First Floor, unit F1-01']], $handoffs);
    }

    public function test_a_bangla_question_asks_the_model_for_a_bangla_answer(): void
    {
        $model = $this->useModel(FakeAnswerGenerator::answering('পার্কিং-এর খরচ প্রথম দুই ঘণ্টায় ৩০ টাকা।'));

        $this->send('পার্কিং-এর খরচ কত?');

        $this->assertSame('bn', $model->requests[0]->language);
        $this->assertSame('পার্কিং-এর খরচ প্রথম দুই ঘণ্টায় ৩০ টাকা।', ChatMessage::query()->orderByDesc('sequence_no')->first()->content);
    }

    public function test_the_model_is_not_asked_when_nothing_relevant_was_found(): void
    {
        $model = $this->useModel(FakeAnswerGenerator::answering('The roof is open for helicopters every Tuesday.'));

        $this->send('Can I land a helicopter on the roof?');

        $this->assertSame([], $model->requests);
        $this->assertStringStartsWith('I could not find that', ChatMessage::query()->orderByDesc('sequence_no')->first()->content);
    }

    public function test_when_the_model_says_the_sources_do_not_answer_the_visitor_is_told_so(): void
    {
        $this->useModel(FakeAnswerGenerator::notKnowing());

        $this->send('What does parking cost?');

        $answer = ChatMessage::query()->orderByDesc('sequence_no')->first();
        $this->assertSame('no_answer', $answer->meta['outcome']);
        $this->assertStringStartsWith('I could not find that in the mall\'s information, so I will not guess.', $answer->content);
        $this->assertSame(0, MessageCitation::query()->count());
    }

    public function test_when_the_model_fails_or_invents_a_figure_the_template_answer_is_stored_with_the_reason(): void
    {
        $this->useModel(FakeAnswerGenerator::failing(GenerationUnavailable::RATE_LIMITED));
        $this->send('What does parking cost?');

        $this->useModel(FakeAnswerGenerator::answering('Parking costs BDT 500 per hour.'));
        $this->send('What does parking cost?');

        $answers = ChatMessage::query()->where('role', 'assistant')->orderBy('sequence_no')->get();
        $this->assertSame(['rate_limited', 'invalid_output'], $answers->map(fn (ChatMessage $answer): string => $answer->meta['fallback_reason'])->all());
        $this->assertSame(['template', 'template'], $answers->map(fn (ChatMessage $answer): string => $answer->meta['driver'])->all());
        foreach ($answers as $answer) {
            $this->assertSame('Parking costs BDT 30 for the first two hours and BDT 20 for every hour after that. [1]', $answer->content);
            $this->assertNull($answer->model_label);
        }
        $this->get($this->page())->assertDontSee('BDT 500');
    }

    public function test_a_restricted_document_never_reaches_the_model_or_the_public_answer(): void
    {
        $this->knowledge($this->mall, 'Security handbook', 'The parking barrier override code is 4471 and costs nothing to use.', scope: 'mall_admin', kind: 'admin_report');
        $model = $this->useModel(FakeAnswerGenerator::answering('Parking costs BDT 30 for the first two hours.'));

        $this->send('What is the parking barrier override code?');

        foreach ($model->requests as $request) {
            foreach ($request->sources as $source) {
                $this->assertTrue($source->isPublic());
                $this->assertStringNotContainsString('4471', $source->text);
            }
        }
        $this->get($this->page())->assertDontSee('4471')->assertDontSee('Security handbook');
    }

    public function test_the_page_says_so_when_answers_may_be_worded_by_a_model(): void
    {
        config(['services.gemini.key' => 'configured']);

        $this->get($this->page())->assertSee('Answers may be worded by an AI model (Google Gemini)');
    }
}
