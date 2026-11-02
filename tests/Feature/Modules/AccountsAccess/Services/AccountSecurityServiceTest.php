<?php

namespace Tests\Feature\Modules\AccountsAccess\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Notifications\PasswordChanged;
use App\Modules\AccountsAccess\Services\AccountSecurityService;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountSecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sessionFor(User $user, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->getKey(), 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    }

    public function test_changing_the_password_keeps_only_the_current_session_and_notifies_the_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $bystander = User::factory()->create();
        $this->sessionFor($user, 'current');
        $this->sessionFor($user, 'laptop');
        $this->sessionFor($bystander, 'bystander');

        app(AccountSecurityService::class)->changePassword($user, 'newMall2026', 'current');

        $this->assertTrue(Hash::check('newMall2026', $user->fresh()->password_hash));
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
        $this->assertEqualsCanonicalizing(['current', 'bystander'], DB::table('sessions')->pluck('id')->all());
        $this->assertSame(['channel' => 'account_page'], AuditLog::query()->where('action', 'account.password_changed')->sole()->metadata);
        Notification::assertSentTo($user, PasswordChanged::class, fn (PasswordChanged $notice): bool => $notice->how === 'change');
    }

    public function test_resetting_the_password_ends_every_session(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->sessionFor($user, 'phone');
        $this->sessionFor($user, 'laptop');

        app(AccountSecurityService::class)->resetPassword($user, 'newMall2026');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->getKey())->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'account.password_reset')->count());
        Notification::assertSentTo($user, PasswordChanged::class, fn (PasswordChanged $notice, array $channels): bool => $notice->how === 'reset' && in_array('mail', $channels, true));
    }
}
