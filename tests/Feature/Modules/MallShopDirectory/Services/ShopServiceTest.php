<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Events\ShopStatusChanged;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Services\ShopService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ShopServiceTest extends TestCase
{
    use RefreshDatabase;

    private function assertRuleViolated(string $rule, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected business rule {$rule}.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }
    }

    public function test_creates_a_draft_shop_with_a_normalised_contact_and_audits_it(): void
    {
        $mall = Mall::factory()->create();

        $shop = app(ShopService::class)->create($mall, ShopCategory::factory()->create(), [
            'slug' => 'cafe-brew', 'name' => 'Cafe Brew', 'contact_phone' => '01700-000001',
        ]);

        $this->assertSame(ShopStatus::Draft, $shop->fresh()->status);
        $this->assertSame('+8801700000001', $shop->fresh()->contact_phone);
        $audit = AuditLog::query()->sole();
        $this->assertSame('shop.created', $audit->action);
        $this->assertSame([$mall->getKey(), $shop->getKey()], [$audit->mall_id, $audit->shop_id]);
    }

    public function test_rejects_invalid_shop_details(): void
    {
        $mall = Mall::factory()->create();
        $category = ShopCategory::factory()->create();
        Shop::factory()->for($mall)->create(['slug' => 'cafe-brew']);
        $service = app(ShopService::class);

        $this->assertRuleViolated('shop.invalid_slug', fn () => $service->create($mall, $category, ['slug' => 'Cafe Brew', 'name' => 'Cafe']));
        $this->assertRuleViolated('shop.slug_taken', fn () => $service->create($mall, $category, ['slug' => 'cafe-brew', 'name' => 'Cafe']));
        $this->assertRuleViolated('shop.invalid_contact_phone', fn () => $service->create($mall, $category, ['slug' => 'juice', 'name' => 'Juice', 'contact_phone' => '12']));
        $this->assertRuleViolated('shop.mall_archived', fn () => $service->create(Mall::factory()->archived()->create(), $category, ['slug' => 'juice', 'name' => 'Juice']));
        $this->assertSame(1, Shop::query()->count());
    }

    public function test_approving_a_draft_audits_the_change_and_announces_it_after_commit(): void
    {
        Event::fake([ShopStatusChanged::class]);
        $shop = Shop::factory()->draft()->create();

        app(ShopService::class)->changeStatus($shop, ShopStatus::Active, 'Approved by mall office');

        $this->assertSame(ShopStatus::Active, $shop->fresh()->status);
        $this->assertSame(
            ['from' => 'draft', 'to' => 'active', 'reason' => 'Approved by mall office'],
            AuditLog::query()->where('action', 'shop.status_changed')->sole()->metadata,
        );
        Event::assertDispatched(ShopStatusChanged::class, fn (ShopStatusChanged $event): bool => $event->shopId === $shop->getKey()
            && $event->mallId === $shop->mall_id
            && $event->from === ShopStatus::Draft
            && $event->to === ShopStatus::Active);
    }

    #[TestWith(['draft', 'closed'])]
    #[TestWith(['archived', 'active'])]
    #[TestWith(['active', 'draft'])]
    public function test_rejects_transitions_outside_the_lifecycle(string $from, string $to): void
    {
        Event::fake([ShopStatusChanged::class]);
        $shop = Shop::factory()->create(['status' => $from]);

        $this->assertRuleViolated('shop.invalid_status_transition', fn () => app(ShopService::class)->changeStatus($shop, ShopStatus::from($to)));

        $this->assertSame($from, $shop->fresh()->status->value);
        Event::assertNotDispatched(ShopStatusChanged::class);
    }
}
