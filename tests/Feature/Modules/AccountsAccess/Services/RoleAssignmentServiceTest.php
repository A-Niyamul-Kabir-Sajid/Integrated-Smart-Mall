<?php

namespace Tests\Feature\Modules\AccountsAccess\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\RoleAssignmentService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RoleAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_a_platform_role_grants_it_and_audits_the_change(): void
    {
        $user = User::factory()->create();

        app(RoleAssignmentService::class)->assignPlatformRole($user, RoleCode::PlatformAdmin);

        $this->assertTrue($user->hasPlatformRole(RoleCode::PlatformAdmin));
        $audit = AuditLog::query()->sole();
        $this->assertSame('access.role_assigned', $audit->action);
        $this->assertSame((string) $user->getKey(), $audit->subject_id);
        $this->assertSame(['role' => 'platform_admin'], $audit->metadata);
    }

    public function test_assigning_a_role_twice_changes_nothing_the_second_time(): void
    {
        $user = User::factory()->create();
        $service = app(RoleAssignmentService::class);

        $service->assignPlatformRole($user, RoleCode::Customer);
        $service->assignPlatformRole($user, RoleCode::Customer);

        $this->assertSame(1, $user->roles()->count());
        $this->assertSame(1, AuditLog::query()->count());
    }

    #[TestWith([RoleCode::MallAdmin])]
    #[TestWith([RoleCode::ShopOwner])]
    #[TestWith([RoleCode::ShopStaff])]
    public function test_mall_and_shop_roles_cannot_be_assigned_directly(RoleCode $code): void
    {
        $user = User::factory()->create();

        try {
            app(RoleAssignmentService::class)->assignPlatformRole($user, $code);
            $this->fail('Expected a business rule violation.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('user_role.not_platform_scope', $violation->rule);
        }

        $this->assertSame(0, $user->roles()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_revoking_a_role_removes_it_and_audits_only_real_changes(): void
    {
        $user = User::factory()->create();
        $service = app(RoleAssignmentService::class);
        $service->assignPlatformRole($user, RoleCode::PlatformAdmin);

        $service->revokePlatformRole($user, RoleCode::PlatformAdmin);
        $service->revokePlatformRole($user, RoleCode::PlatformAdmin);

        $this->assertFalse($user->hasPlatformRole(RoleCode::PlatformAdmin));
        $this->assertEqualsCanonicalizing(['access.role_assigned', 'access.role_revoked'], AuditLog::query()->pluck('action')->all());
    }
}
