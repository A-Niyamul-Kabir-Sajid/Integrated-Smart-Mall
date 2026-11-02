<?php

namespace Tests\Feature\Modules\AccountsAccess\Models;

use App\Modules\AccountsAccess\Enums\AccountStatus;
use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_are_keyed_by_uuid(): void
    {
        $user = User::factory()->create();

        $this->assertSame('user_id', $user->getKeyName());
        $this->assertTrue(Str::isUuid($user->getKey()));
    }

    public function test_email_is_stored_trimmed_and_lower_case(): void
    {
        $user = User::factory()->create(['email' => '  Customer@Example.COM ']);

        $this->assertSame('customer@example.com', $user->fresh()->email);
    }

    public function test_phone_is_stored_in_e164(): void
    {
        $user = User::factory()->phoneOnly('017 1234 5678')->create();

        $this->assertSame('+8801712345678', $user->fresh()->phone);
    }

    public function test_email_must_be_unique_regardless_of_case(): void
    {
        User::factory()->create(['email' => 'shop@example.com']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'SHOP@example.com']);
    }

    public function test_several_accounts_may_have_no_phone(): void
    {
        User::factory()->count(2)->create(['phone' => null]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_password_is_hashed_into_password_hash_and_accepted_by_the_guard(): void
    {
        $user = User::factory()->create(['password_hash' => 'mall2026x']);

        $this->assertTrue(Hash::check('mall2026x', $user->password_hash));
        $this->assertArrayNotHasKey('password_hash', $user->toArray());
        $this->assertTrue(Auth::attempt(['email' => $user->email, 'password' => 'mall2026x']));
    }

    public function test_new_accounts_are_active(): void
    {
        $user = new User;

        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertTrue($user->isActive());
        $this->assertFalse(User::factory()->suspended()->make()->isActive());
    }

    public function test_sessions_reference_accounts_by_uuid(): void
    {
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-1', 'user_id' => $user->getKey(), 'ip_address' => '127.0.0.1',
            'user_agent' => 'Test', 'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);

        $this->assertSame($user->getKey(), DB::table('sessions')->value('user_id'));
    }

    public function test_postgresql_rejects_an_active_account_without_email_or_phone(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The contact check constraint exists on PostgreSQL only; run composer test:pgsql.');
        }

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => null, 'phone' => null]);
    }
}
