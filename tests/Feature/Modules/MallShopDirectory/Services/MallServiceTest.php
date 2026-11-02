<?php

namespace Tests\Feature\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\MallService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MallServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{slug: string, name: string, address: string, city: string, timezone: string}
     */
    private function input(array $overrides = []): array
    {
        return ['slug' => 'demo-city-mall', 'name' => 'Demo City Mall', 'address' => 'Demo Road 32', 'city' => 'Khulna', 'timezone' => 'Asia/Dhaka', ...$overrides];
    }

    private function assertRuleViolated(string $rule, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected business rule {$rule}.");
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame($rule, $violation->rule);
        }
    }

    public function test_creates_a_draft_mall_and_audits_it(): void
    {
        $mall = app(MallService::class)->create($this->input());

        $this->assertSame(MallStatus::Draft, $mall->fresh()->status);
        $audit = AuditLog::query()->sole();
        $this->assertSame('mall.created', $audit->action);
        $this->assertSame($mall->getKey(), $audit->mall_id);
    }

    #[TestWith(['Demo City'])]
    #[TestWith(['demo--city'])]
    #[TestWith(['-demo'])]
    public function test_rejects_malformed_slugs(string $slug): void
    {
        $this->assertRuleViolated('mall.invalid_slug', fn () => app(MallService::class)->create($this->input(['slug' => $slug])));
        $this->assertDatabaseCount('malls', 0);
    }

    public function test_rejects_unknown_time_zones(): void
    {
        $this->assertRuleViolated('mall.invalid_timezone', fn () => app(MallService::class)->create($this->input(['timezone' => 'Asia/Khulna'])));
    }

    public function test_rejects_a_slug_already_in_use(): void
    {
        Mall::factory()->create(['slug' => 'demo-city-mall']);

        $this->assertRuleViolated('mall.slug_taken', fn () => app(MallService::class)->create($this->input()));
    }

    public function test_activating_and_archiving_follow_the_lifecycle_and_are_audited(): void
    {
        $mall = Mall::factory()->draft()->create();
        $service = app(MallService::class);

        $service->changeStatus($mall, MallStatus::Active);
        $service->changeStatus($mall, MallStatus::Archived);

        $this->assertSame(MallStatus::Archived, $mall->fresh()->status);
        $this->assertEqualsCanonicalizing(
            [['from' => 'draft', 'to' => 'active'], ['from' => 'active', 'to' => 'archived']],
            AuditLog::query()->where('action', 'mall.status_changed')->pluck('metadata')->all(),
        );
    }

    public function test_archived_malls_cannot_be_reactivated(): void
    {
        $mall = Mall::factory()->archived()->create();

        $this->assertRuleViolated('mall.invalid_status_transition', fn () => app(MallService::class)->changeStatus($mall, MallStatus::Active));
        $this->assertSame(MallStatus::Archived, $mall->fresh()->status);
    }
}
