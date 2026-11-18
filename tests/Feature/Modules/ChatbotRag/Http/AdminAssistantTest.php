<?php

namespace Tests\Feature\Modules\ChatbotRag\Http;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\AnalyticsReports\Models\MonthlyReport;
use App\Modules\AnalyticsReports\Services\Reporting\MallDashboardService;
use App\Modules\AnalyticsReports\Services\ReportPeriod;
use App\Modules\ChatbotRag\DTOs\AssistantReplyData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Enums\AssistantTool;
use App\Modules\ChatbotRag\Models\ChatMessage;
use App\Modules\ChatbotRag\Models\Conversation;
use App\Modules\ChatbotRag\Services\Assistant\AssistantService;
use App\Modules\ChatbotRag\Services\Assistant\Tools\PeriodParser;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ReviewsModeration\Models\Review;
use Database\Factories\PurchaseFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Modules\ChatbotRag\BuildsAssistantMall;
use Tests\Fixtures\Assistant\FakeAnswerGenerator;
use Tests\TestCase;

/**
 * The administrators' assistant. The clock stands at Monday 2026-11-16 12:00 in the mall (Dhaka),
 * so "last month" is October 2026 and "this month" is 1 to 16 November.
 */
class AdminAssistantTest extends TestCase
{
    use BuildsAssistantMall, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildMall();
        $this->admin = $this->administratorOf($this->mall);
    }

    private function askAsAdmin(string $question, ?User $admin = null): AssistantReplyData
    {
        return app(AssistantService::class)->respond($this->mall, AccessScope::MallAdmin, $admin ?? $this->admin, $question);
    }

    /**
     * @param  list<array{0: ShopProduct, 1: int}>  $lines
     */
    private function sale(Shop $shop, array $lines, string $atUtc): void
    {
        $factory = PurchaseFactory::new()->state(['shop_id' => $shop->getKey(), 'purchased_at' => $atUtc, 'recorded_at' => $atUtc]);

        foreach ($lines as [$listing, $quantity]) {
            $factory = $factory->withLine($listing, $quantity);
        }

        $factory->create();
    }

    private function complaint(string $number, string $category, ?Shop $shop, string $filedAtUtc, string $status = 'open'): void
    {
        DB::table('complaints')->insert([
            'complaint_id' => (string) Str::uuid(), 'complaint_number' => $number, 'mall_id' => $this->mall->getKey(), 'customer_user_id' => User::factory()->create()->getKey(),
            'shop_id' => $shop?->getKey(), 'category' => $category, 'subject' => 'Subject '.$number, 'description' => 'Description '.$number, 'status' => $status,
            'created_at' => $filedAtUtc, 'updated_at' => $filedAtUtc, 'closed_at' => null,
        ]);
    }

    private function page(): string
    {
        return route('assistant.admin.show', $this->mall);
    }

    private function send(string $message)
    {
        return $this->post(route('assistant.admin.messages.store', $this->mall), ['message' => $message]);
    }

    public function test_sales_figures_equal_the_dashboard_of_the_period_and_name_the_period(): void
    {
        $this->sale($this->cafe, [[$this->coldCoffee, 2]], '2026-10-10 05:00:00');
        $this->sale($this->cafe, [[$this->colaAtCafe, 1]], '2026-10-31 17:30:00');
        $this->sale($this->cafe, [[$this->coldCoffee, 5]], '2026-10-31 18:30:00');
        $this->sale($this->mart, [[$this->colaAtMart, 9]], '2026-09-30 17:59:00');

        $reply = $this->askAsAdmin('What were the sales last month?');

        $this->assertSame(AssistantTool::MetricLookup, $reply->tool);
        $this->assertSame(AnswerOutcome::Answered, $reply->outcome);
        $this->assertSame(
            "Recorded sales revenue: BDT 495.00\nRecorded sales: 2 sales\nAverage sale value: BDT 247.50\nUnits sold: 3 units\n"
            .'Period: 1 Oct 2026 to 31 Oct 2026 (31 days, Asia/Dhaka time). Figures read at 16 Nov 2026 12:00.',
            $reply->text,
        );

        $dashboard = app(MallDashboardService::class)->dashboard($this->admin, $this->mall, ReportPeriod::fromInput($this->mall, '2026-10-01', '2026-10-31'));
        $this->assertSame(495.0, (float) $dashboard['metrics']['sales_revenue']);
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-31', 'timezone' => 'Asia/Dhaka'], $reply->meta['period']);
        $this->assertSame('Recorded sales revenue', $reply->meta['definitions'][0]['label']);
        $this->assertStringContainsString('Sum of total_amount of purchases with status completed', $reply->meta['definitions'][0]['formula']);
        $this->assertSame('template', $reply->driver);
    }

    public function test_a_question_without_a_period_uses_the_default_period_and_says_so(): void
    {
        $reply = $this->askAsAdmin('How many orders were placed?');

        $this->assertSame("Orders placed: 0 orders\nPeriod: 18 Oct 2026 to 16 Nov 2026 (30 days, Asia/Dhaka time), the default period because the question named none. Figures read at 16 Nov 2026 12:00.", $reply->text);
    }

    public function test_a_figure_with_nothing_to_calculate_it_from_is_empty_not_zero(): void
    {
        $reply = $this->askAsAdmin('What is the average rating this month?');

        $this->assertStringStartsWith('Average rating: no figure for this period (nothing to calculate it from)', $reply->text);
        $this->assertStringNotContainsString('0.00', $reply->text);
    }

    public function test_several_figures_can_be_asked_at_once_and_the_specific_one_wins(): void
    {
        $this->complaint('C-1', 'noise', null, '2026-11-10 06:00:00');
        $this->complaint('C-2', 'noise', null, '2026-11-11 06:00:00', 'resolved');

        $both = $this->askAsAdmin('How many reservations and open complaints this month?');
        $filed = $this->askAsAdmin('How many complaints this month?');

        $this->assertStringStartsWith("Complaints still open: 1 complaints\nReservations placed: 0 orders\nPeriod: 1 Nov 2026 to 16 Nov 2026", $both->text);
        $this->assertStringStartsWith("Complaints filed: 2 complaints\nPeriod:", $filed->text);
    }

    public function test_complaints_are_ranked_by_shop_and_category_from_the_complaint_records(): void
    {
        $this->complaint('C-1', 'cleanliness', $this->cafe, '2026-11-02 06:00:00');
        $this->complaint('C-2', 'cleanliness', $this->cafe, '2026-11-03 06:00:00');
        $this->complaint('C-3', 'cleanliness', $this->mart, '2026-11-04 06:00:00');
        $this->complaint('C-4', 'noise', $this->books, '2026-11-05 06:00:00');
        $this->complaint('C-5', 'cleanliness', null, '2026-11-06 06:00:00');
        $this->complaint('C-6', 'cleanliness', $this->books, '2026-10-20 06:00:00');

        $cleanliness = $this->askAsAdmin('Which shops received the most cleanliness complaints this month?');
        $all = $this->askAsAdmin('Which shops had the most complaints this month?');
        $byCategory = $this->askAsAdmin('Complaints by category this month');

        $this->assertStringStartsWith("Shops with the most cleanliness complaints:\n• Cafe Brew: 2\n• Fresh Mart: 1\n1 more were about the mall itself, not a shop.\nPeriod: 1 Nov 2026 to 16 Nov 2026", $cleanliness->text);
        $this->assertStringStartsWith("Shops with the most complaints:\n• Cafe Brew: 2\n• Book Nook: 1\n• Fresh Mart: 1\n", $all->text);
        $this->assertStringStartsWith("Complaints filed by category:\n• cleanliness: 4\n• noise: 1\n", $byCategory->text);
    }

    public function test_shops_are_ranked_by_recorded_sales_and_by_rating(): void
    {
        $this->sale($this->cafe, [[$this->coldCoffee, 1]], '2026-11-05 06:00:00');
        $this->sale($this->mart, [[$this->colaAtMart, 2]], '2026-11-05 06:00:00');
        Review::factory()->rated(5)->create(['shop_id' => $this->mart->getKey(), 'created_at' => '2026-11-06 06:00:00']);
        Review::factory()->rated(2)->create(['shop_id' => $this->cafe->getKey(), 'created_at' => '2026-11-06 06:00:00']);

        $top = $this->askAsAdmin('Top shops by sales this month');
        $worstRated = $this->askAsAdmin('Lowest rated shops this month');

        $this->assertStringStartsWith("Shops by recorded sales, highest first:\n• Cafe Brew: BDT 220.00 (73.3% of the mall, 1 sales)\n• Fresh Mart: BDT 80.00 (26.7% of the mall, 1 sales)\n• Book Nook: BDT 0.00 (0.0% of the mall, 0 sales)\n", $top->text);
        $this->assertStringStartsWith("Shops by adjusted rating, lowest first:\n• Cafe Brew: adjusted", $worstRated->text);
        $this->assertStringContainsString('average 2.00 from 1 reviews', $worstRated->text);
    }

    public function test_figures_of_another_mall_are_never_part_of_an_answer(): void
    {
        $other = Mall::factory()->create();
        $theirShop = $this->shopIn($other, 'Far Bazaar', 'groceries', 'Groceries', 'Elsewhere.', null, null, ['09:00', '22:00']);
        $this->sale($theirShop, [[$this->offer($theirShop, 'Far Tea', 'Drinks', 'Tea.', '900.00'), 3]], '2026-11-05 06:00:00');

        $this->assertStringStartsWith('Recorded sales revenue: BDT 0.00', $this->askAsAdmin('What is the revenue this month?')->text);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function questionsWithoutAFigure(): array
    {
        return [
            'not in the catalogue' => ['What is the customer lifetime value this month?', 'it is not a figure of the metric catalogue, so I will not guess'],
            'a figure word without a figure' => ['Give me the figures for happiness', 'I can report only figures of the metric catalogue'],
            'belongs to shops' => ['How many units are in stock?', 'Stock figures belong to each shop'],
            'the future' => ['Forecast the sales for next month', 'I do not estimate future figures'],
            'no data source' => ['How much footfall did we have this week?', 'Navigation requests: Not available yet'],
            'period too long' => ['Revenue from 2020-01-01 to 2026-11-01', 'Choose a period of at most 366 days.'],
        ];
    }

    #[DataProvider('questionsWithoutAFigure')]
    public function test_a_figure_the_assistant_does_not_have_is_named_as_such_and_never_estimated(string $question, string $expected): void
    {
        $reply = $this->askAsAdmin($question);

        $this->assertStringContainsString($expected, $reply->text);
        $this->assertDoesNotMatchRegularExpression('/BDT \d/', $reply->text);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function periodsInWords(): array
    {
        return [
            'today' => ['sales today', '2026-11-16', '2026-11-16'],
            'yesterday' => ['sales yesterday', '2026-11-15', '2026-11-15'],
            'this week' => ['sales this week', '2026-11-16', '2026-11-16'],
            'last week' => ['sales last week', '2026-11-09', '2026-11-15'],
            'this month' => ['sales this month', '2026-11-01', '2026-11-16'],
            'last month' => ['sales last month', '2026-10-01', '2026-10-31'],
            'last days' => ['sales in the last 7 days', '2026-11-10', '2026-11-16'],
            'last weeks' => ['sales in the past 2 weeks', '2026-11-03', '2026-11-16'],
            'named month' => ['sales in october', '2026-10-01', '2026-10-31'],
            'a month that has not come yet this year' => ['sales in december', '2025-12-01', '2025-12-31'],
            'the current month by name' => ['sales in november', '2026-11-01', '2026-11-16'],
            'month and year' => ['sales in february 2026', '2026-02-01', '2026-02-28'],
            'year-month' => ['sales 2026-09', '2026-09-01', '2026-09-30'],
            'two dates' => ['sales from 2026-10-05 to 2026-10-12', '2026-10-05', '2026-10-12'],
            'one date' => ['sales on 2026-10-05', '2026-10-05', '2026-10-05'],
            'no period' => ['sales', '2026-10-18', '2026-11-16'],
        ];
    }

    #[DataProvider('periodsInWords')]
    public function test_periods_in_words_are_whole_days_in_the_malls_time_zone(string $question, string $from, string $to): void
    {
        $period = app(PeriodParser::class)->parse($question, $this->mall)['period'];

        $this->assertSame([$from, $to], [$period->from->format('Y-m-d'), $period->to->format('Y-m-d')]);
    }

    public function test_a_report_draft_shows_the_narrative_and_stores_nothing_until_the_button_is_pressed(): void
    {
        $this->sale($this->cafe, [[$this->coldCoffee, 2]], '2026-10-10 05:00:00');

        $this->actingAs($this->admin)->send('Draft the monthly report for last month')->assertRedirect($this->page().'#latest');

        $answer = ChatMessage::query()->where('role', 'assistant')->sole();
        $this->assertStringStartsWith("Draft of the monthly report for October 2026 (not saved):\n1. Harbour Mall recorded 1 sales worth BDT 440.00 in October 2026", $answer->content);
        $this->assertStringContainsString('[sales_count, sales_revenue, average_sale_value, units_sold]', $answer->content);
        $this->assertStringEndsWith("The figures cover 2026-10-01 to 2026-10-31.\nEvery sentence is built from the recorded figures named in brackets. Nothing has been stored.", $answer->content);
        $this->assertSame(0, MonthlyReport::query()->count());

        $this->get($this->page())
            ->assertSee('Generate and store this report')
            ->assertSee('action="'.route('mall-admin.analytics.reports.store', $this->mall).'"', false)
            ->assertSee('name="month" value="2026-10"', false);

        $this->post(route('mall-admin.analytics.reports.store', $this->mall), ['month' => '2026-10'])->assertRedirect();
        $this->assertSame('2026-10-01', MonthlyReport::query()->sole()->month);
    }

    public function test_a_draft_of_the_current_month_says_the_month_is_not_over(): void
    {
        $reply = $this->askAsAdmin('Write the monthly report');

        $this->assertStringStartsWith('Draft of the monthly report for November 2026 (not saved):', $reply->text);
        $this->assertStringContainsString('The month is not over: the figures run from 2026-11-01 to 2026-11-16.', $reply->text);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requestsToChangeSomething(): array
    {
        return [
            'suspend' => ['Suspend the shop Cafe Brew'],
            'delete' => ['Delete all reviews of Fresh Mart'],
            'approve' => ['Please approve the pending shop application'],
            'price' => ['Change the price of Cold Coffee to 1 taka'],
            'publish' => ['Can you publish an announcement that the mall is closed tomorrow'],
        ];
    }

    #[DataProvider('requestsToChangeSomething')]
    public function test_nothing_is_changed_from_chat_even_for_an_administrator(string $request): void
    {
        $reply = $this->askAsAdmin($request);

        $this->assertSame(AnswerOutcome::Refused, $reply->outcome);
        $this->assertSame(AssistantTool::AdminAction, $reply->tool);
        $this->assertSame(AssistantService::NOT_PERMITTED, $reply->refusalReason);
        $this->assertStringStartsWith('I cannot change anything from this chat.', $reply->text);
        $this->assertSame(ShopStatus::Active, $this->cafe->refresh()->status);
        $this->assertSame('220.00', $this->coldCoffee->refresh()->price_amount);
        $this->assertSame(route('mall-admin.dashboard', $this->mall), $reply->meta['links'][0]['url']);
    }

    public function test_an_administrator_reads_internal_documents_and_no_model_is_ever_asked(): void
    {
        $this->knowledge($this->mall, 'Security handbook', 'The security control room code word is BLUE HERON.', scope: 'mall_admin', kind: 'admin_report');
        $model = $this->useModel(FakeAnswerGenerator::answering('A model must never see this.'));

        $internal = $this->askAsAdmin('What is the security control room code word?');
        $public = $this->askAsAdmin('What does parking cost?');

        $this->assertSame('The security control room code word is BLUE HERON. [1]', $internal->text);
        $this->assertSame('Parking costs BDT 30 for the first two hours and BDT 20 for every hour after that. [1]', $public->text);
        $this->assertSame(['template', 'template'], [$internal->driver, $public->driver]);
        $this->assertSame([], $model->requests);
    }

    public function test_an_administrator_of_another_mall_gets_no_figures_or_documents_of_this_mall(): void
    {
        $this->knowledge($this->mall, 'Security handbook', 'The security control room code word is BLUE HERON.', scope: 'mall_admin', kind: 'admin_report');
        $otherMall = Mall::factory()->create();
        $otherAdmin = $this->administratorOf($otherMall);

        $elsewhere = app(AssistantService::class)->respond($otherMall, AccessScope::MallAdmin, $otherAdmin, 'What is the security control room code word?');
        $this->assertStringNotContainsString('BLUE HERON', $elsewhere->text);

        $this->expectException(AuthorizationException::class);
        $this->askAsAdmin('What were the sales last month?', $otherAdmin);
    }

    public function test_the_page_shows_the_conversation_with_definitions_and_links(): void
    {
        $this->actingAs($this->admin)->get($this->page())->assertOk()->assertSee('What were the sales last month?')->assertSee('Answers here are never written by an AI model.');

        $this->send('What is the revenue this month?');

        $this->get($this->page())
            ->assertSeeInOrder(['What is the revenue this month?', 'Recorded sales revenue: BDT 0.00', 'How these figures are counted', 'Sum of total_amount of purchases', 'Dashboard for this period', 'How each figure is calculated'])
            ->assertSee(route('mall-admin.analytics.index', ['mall' => $this->mall, 'from' => '2026-11-01', 'to' => '2026-11-16']));

        $conversation = Conversation::query()->sole();
        $this->assertSame(AccessScope::MallAdmin, $conversation->access_scope);
        $this->assertSame($this->admin->getKey(), $conversation->user_id);
    }

    public function test_each_administrator_has_a_private_conversation_separate_from_the_public_one(): void
    {
        $colleague = User::factory()->create();
        app(MembershipService::class)->grantMallRole($colleague, $this->mall, RoleCode::MallAdmin);

        $this->actingAs($this->admin)->send('What is the revenue this month?');
        $this->post(route('assistant.messages.store', ['publicMall' => 'harbour-mall']), ['message' => 'What does parking cost?']);

        $this->get($this->page())->assertSee('Recorded sales revenue')->assertDontSee('Parking costs BDT 30');
        $this->get(route('assistant.show', ['publicMall' => 'harbour-mall']))->assertSee('Parking costs BDT 30')->assertDontSee('Recorded sales revenue');

        $this->flushSession();
        $this->actingAs($colleague)->get($this->page())->assertDontSee('Recorded sales revenue')->assertSee('Try asking');
        $this->withSession(['assistant.conversations.mall_admin.'.$this->mall->getKey() => Conversation::query()->where('access_scope', 'mall_admin')->sole()->getKey()])
            ->get($this->page())->assertDontSee('Recorded sales revenue');
    }

    public function test_the_same_question_in_the_public_assistant_is_refused_for_the_same_administrator(): void
    {
        $this->actingAs($this->admin)->post(route('assistant.messages.store', ['publicMall' => 'harbour-mall']), ['message' => 'What were the sales last month?']);

        $answer = ChatMessage::query()->where('role', 'assistant')->sole();
        $this->assertSame('refused', $answer->meta['outcome']);
        $this->assertStringStartsWith('Figures about sales, orders, complaints and other business records are available only to the mall\'s administrators', $answer->content);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function peopleWithoutTheMall(): array
    {
        return ['customer' => ['customer'], 'shop owner in the mall' => ['owner'], 'shop staff in the mall' => ['staff'], 'administrator of another mall' => ['other-admin']];
    }

    #[DataProvider('peopleWithoutTheMall')]
    public function test_only_administrators_of_the_mall_reach_the_administrators_assistant(string $who): void
    {
        $user = User::factory()->create();

        match ($who) {
            'owner' => app(MembershipService::class)->grantShopRole($user, $this->cafe, RoleCode::ShopOwner),
            'staff' => app(MembershipService::class)->grantShopRole($user, $this->cafe, RoleCode::ShopStaff),
            'other-admin' => app(MembershipService::class)->grantMallRole($user, Mall::factory()->create(), RoleCode::MallAdmin),
            default => null,
        };

        $this->actingAs($user)->get($this->page())->assertForbidden();
        $this->send('What were the sales last month?')->assertForbidden();
        $this->delete(route('assistant.admin.destroy', $this->mall))->assertForbidden();
        $this->get(route('assistant.admin.activity', $this->mall))->assertForbidden();

        $this->assertSame(0, Conversation::query()->count());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get($this->page())->assertRedirect(route('login'));
        $this->send('What were the sales last month?')->assertRedirect(route('login'));
        $this->get(route('assistant.admin.activity', $this->mall))->assertRedirect(route('login'));

        $this->assertSame(0, Conversation::query()->count());
    }

    public function test_the_workspace_home_links_to_the_assistant_pages(): void
    {
        $this->actingAs($this->admin)->get(route('mall-admin.dashboard', $this->mall))
            ->assertSee('href="'.$this->page().'"', false)
            ->assertSee('href="'.route('assistant.admin.knowledge.index', $this->mall).'"', false)
            ->assertSee('href="'.route('assistant.admin.activity', $this->mall).'"', false);
    }
}
