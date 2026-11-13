<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ProductsCatalogue\Models\ShopProduct;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\PromotionRedemption;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use App\Modules\ShopOwnerOperations\Models\ShopPromotion;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Services\OrderService;
use App\Modules\ShopOwnerOperations\Services\PromotionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private User $customer;

    private ShopProduct $coffee;

    private PromotionService $promotions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['name' => 'Cafe Brew']);
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
        $this->customer = User::factory()->create();
        $this->coffee = ShopProduct::factory()->for($this->shop)->create(['price_amount' => '100.00']);
        $this->promotions = app(PromotionService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'title' => 'Opening week',
            'description' => 'Thanks for visiting',
            'discount_type' => 'percent',
            'discount_value' => '10',
            'coupon_code' => '',
            'min_subtotal' => '0',
            'starts_at' => now('Asia/Dhaka')->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now('Asia/Dhaka')->addDays(7)->format('Y-m-d\TH:i'),
            'usage_limit' => '',
            'per_customer_limit' => '1',
            ...$overrides,
        ];
    }

    private function order(?string $coupon = null, int $quantity = 2, ?User $customer = null): ShopOrder
    {
        return app(OrderService::class)->place($customer ?? $this->customer, $this->shop, [
            'items' => [['shop_product_id' => $this->coffee->getKey(), 'quantity' => $quantity]],
            'kind' => 'reservation',
            'pickup_by' => now()->addHours(3),
            'coupon_code' => $coupon,
        ]);
    }

    private function rule(callable $action): string
    {
        try {
            $action();
        } catch (BusinessRuleViolation $violation) {
            return $violation->rule;
        }

        $this->fail('Expected a business rule violation.');
    }

    public function test_the_owner_creates_edits_pauses_and_ends_a_promotion(): void
    {
        $this->actingAs($this->owner)->get(route('workspace.promotions.index', $this->shop))->assertOk()->assertSee('No promotions yet.');
        $this->get(route('workspace.promotions.create', $this->shop))->assertOk();

        $this->post(route('workspace.promotions.store', $this->shop), $this->form(['coupon_code' => 'welcome-10']))->assertRedirect(route('workspace.promotions.index', $this->shop));
        $promotion = ShopPromotion::query()->sole();
        $this->assertSame('WELCOME-10', $promotion->coupon_code);
        $this->assertTrue($promotion->isRunning());
        $this->get(route('workspace.promotions.index', $this->shop))->assertSee('Opening week')->assertSee('WELCOME-10')->assertSee('Running');

        $this->get(route('workspace.promotions.edit', [$this->shop, $promotion]))->assertOk()->assertSee('value="Opening week"', false);
        $this->put(route('workspace.promotions.update', [$this->shop, $promotion]), $this->form(['title' => 'Opening month', 'coupon_code' => 'WELCOME-10', 'discount_value' => '15']))->assertRedirect();
        $this->assertSame('Opening month', $promotion->fresh()->title);

        $this->post(route('workspace.promotions.pause', [$this->shop, $promotion]))->assertRedirect();
        $this->assertFalse($promotion->fresh()->isRunning());
        $this->post(route('workspace.promotions.resume', [$this->shop, $promotion]))->assertRedirect();
        $this->assertTrue($promotion->fresh()->isRunning());

        $this->post(route('workspace.promotions.end', [$this->shop, $promotion]))->assertRedirect();
        $this->assertFalse($promotion->fresh()->isRunning());
        $this->post(route('workspace.promotions.end', [$this->shop, $promotion]))->assertSessionHasErrors('business');
    }

    public function test_invalid_promotions_are_refused(): void
    {
        $cases = [
            'title' => ['title' => 'ab'],
            'invalid_value#zero' => ['discount_value' => '0'],
            'invalid_value#percent' => ['discount_value' => '95'],
            'invalid_value#amount' => ['discount_type' => 'amount_off', 'discount_value' => '9999999'],
            'invalid_code_format' => ['coupon_code' => 'a b!'],
            'invalid_dates#order' => ['ends_at' => now('Asia/Dhaka')->subDay()->format('Y-m-d\TH:i')],
            'invalid_dates#far' => ['ends_at' => now('Asia/Dhaka')->addYears(5)->format('Y-m-d\TH:i')],
            'invalid_limit' => ['per_customer_limit' => '50'],
        ];

        foreach ($cases as $overrides) {
            $this->actingAs($this->owner)->post(route('workspace.promotions.store', $this->shop), $this->form($overrides))->assertSessionHasErrors();
        }

        $this->assertSame(0, ShopPromotion::query()->count());
        $this->assertSame('promotion.invalid_value', $this->rule(fn () => $this->promotions->create($this->owner, $this->shop, $this->form(['discount_value' => '95']))));
        $this->assertSame('promotion.invalid_dates', $this->rule(fn () => $this->promotions->create($this->owner, $this->shop, $this->form(['ends_at' => 'junk']))));
    }

    public function test_a_coupon_code_is_unique_within_the_shop_only(): void
    {
        $this->promotions->create($this->owner, $this->shop, $this->form(['coupon_code' => 'SAVE']));

        $this->assertSame('promotion.code_taken', $this->rule(fn () => $this->promotions->create($this->owner, $this->shop, $this->form(['coupon_code' => 'save']))));

        $other = Shop::factory()->create();
        $otherOwner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $other, RoleCode::ShopOwner);
        $this->promotions->create($otherOwner, $other, $this->form(['coupon_code' => 'SAVE']));
        $this->assertSame(2, ShopPromotion::query()->count());
    }

    public function test_a_storewide_offer_applies_automatically_and_the_best_one_wins(): void
    {
        ShopPromotion::factory()->for($this->shop)->create(['title' => 'Ten percent', 'discount_value' => '10.00']);
        ShopPromotion::factory()->for($this->shop)->amountOff('25.00')->create(['title' => 'Flat 25']);
        ShopPromotion::factory()->for($this->shop)->ended()->create(['title' => 'Old offer', 'discount_value' => '50.00']);

        $order = $this->order();

        $this->assertSame('200.00', (string) $order->subtotal_amount);
        $this->assertSame('25.00', (string) $order->discount_amount);
        $this->assertSame('175.00', (string) $order->total_amount);
        $this->assertSame('Flat 25', $order->promotion_title);
        $this->assertNull($order->coupon_code);
        $this->assertSame(1, PromotionRedemption::query()->count());
    }

    public function test_a_coupon_applies_only_when_entered_and_is_counted_once_per_customer(): void
    {
        $promotion = ShopPromotion::factory()->for($this->shop)->coupon('SAVE10')->create(['discount_value' => '10.00', 'min_subtotal' => '150.00']);

        $this->assertSame('0.00', (string) $this->order()->discount_amount);
        $this->assertSame('promotion.invalid_code', $this->rule(fn () => $this->order('WRONG')));
        $this->assertSame('promotion.min_subtotal', $this->rule(fn () => $this->order('save10', 1)));

        $order = $this->order(' save10 ');
        $this->assertSame('20.00', (string) $order->discount_amount);
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame(1, $promotion->fresh()->times_redeemed);

        $this->assertSame('promotion.already_used', $this->rule(fn () => $this->order('SAVE10')));
        $this->assertSame(1, $promotion->fresh()->times_redeemed);

        $this->assertSame('20.00', (string) $this->order('SAVE10', 2, User::factory()->create())->discount_amount);
    }

    public function test_the_total_usage_limit_and_inactive_promotions_are_respected(): void
    {
        $limited = ShopPromotion::factory()->for($this->shop)->coupon('ONCE')->create(['usage_limit' => 1]);
        $this->order('ONCE');
        $this->assertSame('promotion.invalid_code', $this->rule(fn () => $this->order('ONCE', 2, User::factory()->create())));

        $limited->update(['usage_limit' => 5, 'is_paused' => true]);
        $this->assertSame('promotion.invalid_code', $this->rule(fn () => $this->order('ONCE', 2, User::factory()->create())));
        $this->assertSame(1, ShopOrder::query()->count());
    }

    public function test_a_cancelled_order_gives_the_coupon_use_back(): void
    {
        $promotion = ShopPromotion::factory()->for($this->shop)->coupon('SAVE10')->create();
        $order = $this->order('SAVE10');
        $this->assertSame(1, $promotion->fresh()->times_redeemed);

        app(OrderService::class)->cancel($this->customer, $order);

        $this->assertSame(0, $promotion->fresh()->times_redeemed);
        $this->assertNotNull(PromotionRedemption::query()->sole()->released_at);
        $this->assertSame('20.00', (string) $this->order('SAVE10', 2)->discount_amount);
        $this->assertSame(1, $promotion->fresh()->times_redeemed);
    }

    public function test_the_completed_sale_carries_the_order_discount(): void
    {
        ShopPromotion::factory()->for($this->shop)->create(['discount_value' => '10.00']);
        $manager = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($manager, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::Manager]);
        $order = $this->order();
        app(OrderService::class)->accept($manager, $order);
        app(OrderService::class)->complete($manager, $order, $order->pickup_code);

        $purchase = $order->fresh()->purchase;
        $this->assertSame('180.00', (string) $purchase->total_amount);
        $this->assertSame('20.00', (string) $purchase->items->first()->line_discount_amount);
    }

    public function test_customers_see_storewide_offers_on_the_shop_page_but_never_coupon_codes(): void
    {
        ShopPromotion::factory()->for($this->shop)->create(['title' => 'Eid special', 'description' => 'Warm welcome']);
        ShopPromotion::factory()->for($this->shop)->coupon('SECRET99')->create(['title' => 'Members only']);
        ShopPromotion::factory()->for($this->shop)->ended()->create(['title' => 'Last month']);
        $url = ['publicMall' => $this->shop->mall->slug, 'publicShop' => $this->shop->slug];

        $this->get(route('shops.show', $url))->assertOk()->assertSee('Eid special')->assertSee('Warm welcome')
            ->assertDontSee('Members only')->assertDontSee('SECRET99')->assertDontSee('Last month');

        $this->shop->update(['status' => 'closed']);
        $this->get(route('shops.show', $url))->assertDontSee('Eid special');
    }

    public function test_only_managers_and_owners_of_this_shop_reach_promotions(): void
    {
        $promotion = ShopPromotion::factory()->for($this->shop)->create();
        $cashier = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($cashier, $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => ShopFunction::Cashier]);
        $otherShop = Shop::factory()->create();
        $otherOwner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);

        $this->get(route('workspace.promotions.index', $this->shop))->assertRedirect(route('login'));

        foreach ([$cashier, $this->customer, $otherOwner] as $user) {
            $this->actingAs($user)->get(route('workspace.promotions.index', $this->shop))->assertForbidden();
            $this->post(route('workspace.promotions.store', $this->shop), $this->form())->assertForbidden();
            $this->post(route('workspace.promotions.pause', [$this->shop, $promotion]))->assertForbidden();
        }

        $this->actingAs($otherOwner)->get(route('workspace.promotions.edit', [$otherShop, $promotion]))->assertNotFound();

        $this->expectException(AuthorizationException::class);
        $this->promotions->pause($cashier, $promotion);
    }
}
