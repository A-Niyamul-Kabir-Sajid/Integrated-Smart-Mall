<?php

namespace Tests\Feature\Modules\AccountsAccess\Http\Middleware;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])
            ->get('/_test/shops/{shop}/workspace', fn (Shop $shop) => 'workspace of '.$shop->name)
            ->can(Ability::WorkInShop->value, 'shop');
    }

    private function staffOf(Shop $shop): User
    {
        $user = User::factory()->create();
        app(MembershipService::class)->grantShopRole($user, $shop, RoleCode::ShopStaff);

        return $user;
    }

    public function test_staff_reach_their_own_shops_protected_page(): void
    {
        $shop = Shop::factory()->create(['name' => 'Cafe Brew']);

        $this->actingAs($this->staffOf($shop))
            ->get("/_test/shops/{$shop->getKey()}/workspace")
            ->assertOk()
            ->assertSee('workspace of Cafe Brew');
    }

    public function test_staff_of_another_shop_are_forbidden(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAs($this->staffOf(Shop::factory()->create()))
            ->get("/_test/shops/{$shop->getKey()}/workspace")
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $shop = Shop::factory()->create();

        $this->get("/_test/shops/{$shop->getKey()}/workspace")->assertRedirect(route('login'));
    }

    public function test_an_account_suspended_while_signed_in_is_signed_out_on_its_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account.show'))->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->get(route('account.show'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login' => 'Your account is not active. Contact the mall information desk.']);
        $this->assertGuest();
    }
}
