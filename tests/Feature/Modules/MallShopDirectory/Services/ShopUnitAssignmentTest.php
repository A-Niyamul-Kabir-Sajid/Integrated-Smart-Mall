<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\ShopService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopUnitAssignmentTest extends TestCase
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

    public function test_assigns_an_open_unit_on_a_floor_of_the_shops_mall(): void
    {
        $shop = Shop::factory()->create();
        $floor = Floor::factory()->for($shop->mall)->create(['code' => 'G']);

        $unit = app(ShopService::class)->assignUnit($shop, $floor, ' g-05 ', 137.5);

        $this->assertSame('G-05', $unit->unit_code);
        $this->assertSame('open', $unit->status);
        $this->assertSame('137.50', $unit->fresh()->area_sq_m);
        $this->assertSame(['unit_code' => 'G-05', 'floor' => 'G'], AuditLog::query()->sole()->metadata);
    }

    public function test_rejects_a_floor_of_another_mall(): void
    {
        $shop = Shop::factory()->create();
        $foreignFloor = Floor::factory()->create();

        $this->assertRuleViolated('shop_unit.floor_in_other_mall', fn () => app(ShopService::class)->assignUnit($shop, $foreignFloor, 'G-01'));
        $this->assertSame(0, ShopUnit::query()->count());
    }

    public function test_rejects_archived_floors_bad_codes_taken_codes_and_non_positive_areas(): void
    {
        $shop = Shop::factory()->create();
        $floor = Floor::factory()->for($shop->mall)->create();
        $archived = Floor::factory()->for($shop->mall)->create(['status' => 'archived']);
        ShopUnit::factory()->for(Shop::factory()->for($shop->mall))->for($floor)->create(['unit_code' => 'G-01']);
        $service = app(ShopService::class);

        $this->assertRuleViolated('shop_unit.floor_archived', fn () => $service->assignUnit($shop, $archived, 'F9-01'));
        $this->assertRuleViolated('shop_unit.invalid_code', fn () => $service->assignUnit($shop, $floor, 'unit 7'));
        $this->assertRuleViolated('shop_unit.code_taken', fn () => $service->assignUnit($shop, $floor, 'g-01'));
        $this->assertRuleViolated('shop_unit.invalid_area', fn () => $service->assignUnit($shop, $floor, 'G-02', 0));
        $this->assertSame(0, $shop->units()->count());
    }
}
