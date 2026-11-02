<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\FloorService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FloorServiceTest extends TestCase
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

    public function test_adds_an_active_floor_with_an_upper_case_code_and_audits_it(): void
    {
        $mall = Mall::factory()->create();

        $floor = app(FloorService::class)->addFloor($mall, ' f1 ', 'First Floor', 1, 4.5);

        $this->assertSame('F1', $floor->code);
        $this->assertSame('active', $floor->fresh()->status);
        $this->assertSame('4.500', $floor->fresh()->elevation_m);
        $this->assertSame($mall->getKey(), AuditLog::query()->sole()->mall_id);
    }

    #[TestWith(['Level 1'])]
    #[TestWith(['ABCDEFGHIJKLMNOPQRSTU'])]
    #[TestWith(['F-1'])]
    #[TestWith([''])]
    public function test_rejects_codes_that_are_not_short_alphanumerics(string $code): void
    {
        $this->assertRuleViolated('floor.invalid_code', fn () => app(FloorService::class)->addFloor(Mall::factory()->create(), $code, 'Floor', 0, 0));
    }

    public function test_rejects_a_code_the_mall_already_uses(): void
    {
        $mall = Mall::factory()->create();
        Floor::factory()->for($mall)->create(['code' => 'G']);

        $this->assertRuleViolated('floor.code_taken', fn () => app(FloorService::class)->addFloor($mall, 'g', 'Ground', 0, 0));
    }

    public function test_rejects_floors_for_archived_malls(): void
    {
        $this->assertRuleViolated('floor.mall_archived', fn () => app(FloorService::class)->addFloor(Mall::factory()->archived()->create(), 'G', 'Ground', 0, 0));
        $this->assertDatabaseCount('floors', 0);
    }
}
