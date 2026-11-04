<?php

namespace Tests\Feature\Modules\AccountsAccess\Models;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\Role;
use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_role_catalogue_exists_with_the_scope_of_each_code(): void
    {
        $this->assertSame(count(RoleCode::cases()), Role::query()->count());

        foreach (RoleCode::cases() as $code) {
            $this->assertSame($code->scope(), Role::forCode($code)->scope_type, "{$code->value} has the wrong scope.");
        }
    }

    public function test_user_roles_lists_the_platform_roles_of_an_account(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::forCode(RoleCode::Customer), ['assigned_at' => now()]);

        $this->assertTrue($user->hasPlatformRole(RoleCode::Customer));
        $this->assertFalse($user->hasPlatformRole(RoleCode::PlatformAdmin));
        $this->assertSame([RoleCode::Customer], $user->roles()->get()->pluck('code')->all());
    }

    public function test_an_account_holds_each_role_at_most_once(): void
    {
        $user = User::factory()->create();
        $customer = Role::forCode(RoleCode::Customer);
        $user->roles()->attach($customer, ['assigned_at' => now()]);

        $this->expectException(QueryException::class);

        $user->roles()->attach($customer, ['assigned_at' => now()]);
    }
}
