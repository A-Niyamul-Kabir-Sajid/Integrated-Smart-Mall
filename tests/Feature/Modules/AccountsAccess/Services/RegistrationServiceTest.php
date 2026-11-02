<?php

namespace Tests\Feature\Modules\AccountsAccess\Services;

use App\Modules\AccountsAccess\Enums\AccountStatus;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Services\RegistrationService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_an_active_customer_with_a_hashed_password(): void
    {
        Event::fake([Registered::class]);

        $user = app(RegistrationService::class)->registerCustomer([
            'display_name' => 'Nusrat Jahan',
            'email' => 'nusrat@example.com',
            'phone' => null,
            'password' => 'mall2026x',
        ]);

        $this->assertSame(AccountStatus::Active, $user->fresh()->status);
        $this->assertTrue($user->hasPlatformRole(RoleCode::Customer));
        $this->assertTrue(Hash::check('mall2026x', $user->fresh()->password_hash));
        $this->assertNotSame('mall2026x', $user->fresh()->password_hash);
        Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
    }

    public function test_audits_the_registration_channel_without_personal_secrets(): void
    {
        $user = app(RegistrationService::class)->registerCustomer([
            'display_name' => 'Phone Customer',
            'phone' => '+8801812345678',
            'password' => 'mall2026x',
        ]);

        $registered = AuditLog::query()->where('action', 'account.registered')->sole();
        $this->assertSame((string) $user->getKey(), $registered->subject_id);
        $this->assertSame(['channel' => 'phone'], $registered->metadata);
        $this->assertSame(1, AuditLog::query()->where('action', 'access.role_assigned')->count());
    }
}
