<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopProfileTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['name' => 'Old Name']);
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
    }

    public function test_the_owner_edits_the_profile_and_the_slug_stays_put(): void
    {
        $slug = $this->shop->slug;

        $this->actingAs($this->owner)->get(route('workspace.profile.edit', $this->shop))->assertOk()->assertSee('Old Name');
        $this->put(route('workspace.profile.update', $this->shop), ['name' => 'New Name', 'description' => 'Fresh text.', 'contact_phone' => '01712345678', 'slug' => 'hacked', 'status' => 'archived'])
            ->assertRedirect()->assertSessionHas('status');

        $shop = $this->shop->refresh();
        $this->assertSame('New Name', $shop->name);
        $this->assertSame('+8801712345678', $shop->contact_phone);
        $this->assertSame($slug, $shop->slug);
        $this->assertSame('active', $shop->status->value);
    }

    public function test_invalid_profile_values_are_refused(): void
    {
        $this->actingAs($this->owner);

        $this->put(route('workspace.profile.update', $this->shop), ['name' => ''])->assertSessionHasErrors('name');
        $this->put(route('workspace.profile.update', $this->shop), ['name' => 'Fine', 'contact_phone' => 'abc'])->assertSessionHasErrors('business');
        $this->assertSame('Old Name', $this->shop->refresh()->name);
    }

    public function test_the_owner_sets_weekly_opening_hours(): void
    {
        $this->actingAs($this->owner)->put(route('workspace.hours.update', $this->shop), ['hours' => [
            '1' => ['opens_at' => '10:00', 'closes_at' => '21:00'],
            '2' => ['opens_at' => '', 'closes_at' => ''],
            '5' => ['opens_at' => '18:00', 'closes_at' => '02:00', 'closes_next_day' => '1'],
        ]])->assertRedirect()->assertSessionHas('status');

        $rows = $this->shop->openingHours()->get();
        $this->assertCount(2, $rows);
        $this->assertSame([1, 5], $rows->pluck('weekday')->all());
        $this->assertTrue((bool) $rows->last()->closes_next_day);

        $this->get(route('workspace.profile.edit', $this->shop))->assertSee('value="10:00"', false);
    }

    public function test_incomplete_or_overnight_without_flag_hours_are_refused(): void
    {
        $this->actingAs($this->owner);

        $this->put(route('workspace.hours.update', $this->shop), ['hours' => ['1' => ['opens_at' => '10:00', 'closes_at' => '']]])->assertSessionHasErrors('business');
        $this->put(route('workspace.hours.update', $this->shop), ['hours' => ['1' => ['opens_at' => '22:00', 'closes_at' => '06:00']]])->assertSessionHasErrors('business');
        $this->put(route('workspace.hours.update', $this->shop), ['hours' => ['9' => ['opens_at' => '10:00', 'closes_at' => '12:00']]])->assertSessionHasErrors('business');
        $this->assertCount(0, $this->shop->openingHours()->get());
    }

    public function test_others_cannot_change_the_profile(): void
    {
        $this->put(route('workspace.profile.update', $this->shop), ['name' => 'Hijack'])->assertRedirect(route('login'));

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->put(route('workspace.profile.update', $this->shop), ['name' => 'Hijack'])->assertForbidden();

        $otherOwner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, Shop::factory()->create(), RoleCode::ShopOwner);
        $this->actingAs($otherOwner)->put(route('workspace.hours.update', $this->shop), ['hours' => []])->assertForbidden();

        $this->assertSame('Old Name', $this->shop->refresh()->name);
    }

    public function test_the_workspace_home_lists_the_areas_for_the_member(): void
    {
        $this->actingAs($this->owner)->get(route('workspace.dashboard', $this->shop))
            ->assertOk()->assertSee('Catalogue')->assertSee('Staff')->assertSee('Shop profile and opening hours');

        $this->get(route('workspace.dashboard', Shop::factory()->create()))->assertForbidden();
    }
}
