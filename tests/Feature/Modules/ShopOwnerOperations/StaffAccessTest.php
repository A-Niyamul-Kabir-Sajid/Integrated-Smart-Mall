<?php

namespace Tests\Feature\Modules\ShopOwnerOperations;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\ShopMembership;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\ShopOwnerOperations\Enums\InvitationStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Models\ShopStaffInvitation;
use App\Modules\ShopOwnerOperations\Notifications\StaffInvitationNotice;
use App\Modules\ShopOwnerOperations\Services\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        $this->owner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($this->owner, $this->shop, RoleCode::ShopOwner);
    }

    private function staffMember(ShopFunction $function, ?Shop $shop = null): User
    {
        $user = User::factory()->create();
        $membership = app(MembershipService::class)->grantShopRole($user, $shop ?? $this->shop, RoleCode::ShopStaff);
        ShopStaffFunction::query()->create(['shop_membership_id' => $membership->getKey(), 'staff_function' => $function]);

        return $user;
    }

    public function test_the_owner_invites_by_email_and_the_invited_person_accepts_once(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->post(route('workspace.staff.invite', $this->shop), ['email' => 'New.Cashier@Example.com', 'staff_function' => 'cashier'])
            ->assertRedirect(route('workspace.staff.index', $this->shop))
            ->assertSessionHas('invitation_link');

        Notification::assertSentOnDemand(StaffInvitationNotice::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new.cashier@example.com');

        $link = session('invitation_link');
        $secret = basename($link);
        $invitation = ShopStaffInvitation::query()->sole();
        $this->assertSame(hash('sha256', $secret), $invitation->secret_hash);
        $this->assertStringNotContainsString($secret, json_encode($invitation->toArray()));
        $this->get(route('workspace.staff.index', $this->shop))->assertSee('new.cashier@example.com');

        $cashier = User::factory()->create(['email' => 'new.cashier@example.com']);
        $this->actingAs($cashier)->get($link)->assertOk()->assertSee($this->shop->name)->assertSee('Cashier');
        $this->post(route('staff-invitations.accept', $secret))->assertRedirect(route('workspace.dashboard', $this->shop));

        $this->assertTrue($cashier->hasShopRole($this->shop->getKey(), RoleCode::ShopStaff));
        $this->assertSame(InvitationStatus::Accepted, $invitation->refresh()->status);
        $this->assertTrue(Gate::forUser($cashier)->allows(ShopArea::Orders->ability(), $this->shop));
        $this->assertFalse(Gate::forUser($cashier)->allows(ShopArea::Promotions->ability(), $this->shop));

        $this->post(route('staff-invitations.accept', $secret))->assertSessionHasErrors('business');
    }

    public function test_an_invitation_only_works_for_the_invited_email_and_before_it_expires(): void
    {
        ['secret' => $secret, 'invitation' => $invitation] = app(StaffService::class)->invite($this->owner, $this->shop, 'invited@example.com', ShopFunction::Manager);

        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $this->actingAs($stranger)->get(route('staff-invitations.show', $secret))->assertOk()->assertSee('different email address');
        $this->post(route('staff-invitations.accept', $secret))->assertSessionHasErrors('business');
        $this->assertFalse($stranger->hasShopRole($this->shop->getKey(), RoleCode::ShopStaff));

        $invited = User::factory()->create(['email' => 'invited@example.com']);
        $invitation->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($invited)->post(route('staff-invitations.accept', $secret))->assertSessionHasErrors('business');
        $this->get(route('staff-invitations.show', $secret))->assertSee('cannot be used');

        $this->get(route('staff-invitations.show', 'A'.str_repeat('b', 39)))->assertOk()->assertSee('cannot be used');
        $this->get('/staff-invitations/not-valid!')->assertNotFound();
    }

    public function test_revoked_invitations_cannot_be_accepted_and_other_shops_cannot_revoke_them(): void
    {
        ['secret' => $secret, 'invitation' => $invitation] = app(StaffService::class)->invite($this->owner, $this->shop, 'later@example.com', ShopFunction::Cashier);

        $otherShop = Shop::factory()->create();
        $otherOwner = User::factory()->create();
        app(MembershipService::class)->grantShopRole($otherOwner, $otherShop, RoleCode::ShopOwner);
        $this->actingAs($otherOwner)->delete(route('workspace.staff.invitations.revoke', [$otherShop, $invitation]))->assertNotFound();
        $this->delete(route('workspace.staff.invitations.revoke', [$this->shop, $invitation]))->assertForbidden();

        $this->actingAs($this->owner)->delete(route('workspace.staff.invitations.revoke', [$this->shop, $invitation]))->assertRedirect();
        $this->assertSame(InvitationStatus::Revoked, $invitation->refresh()->status);

        $later = User::factory()->create(['email' => 'later@example.com']);
        $this->actingAs($later)->post(route('staff-invitations.accept', $secret))->assertSessionHasErrors('business');
    }

    public function test_invitation_rules_are_enforced(): void
    {
        $service = app(StaffService::class);
        $member = $this->staffMember(ShopFunction::Cashier);

        $this->actingAs($this->owner)->post(route('workspace.staff.invite', $this->shop), ['email' => 'not-an-email', 'staff_function' => 'cashier'])->assertSessionHasErrors('business');
        $this->post(route('workspace.staff.invite', $this->shop), ['email' => $member->email, 'staff_function' => 'cashier'])->assertSessionHasErrors('business');
        $this->post(route('workspace.staff.invite', $this->shop), ['email' => 'x@example.com', 'staff_function' => 'owner'])->assertSessionHasErrors('staff_function');

        $service->invite($this->owner, $this->shop, 'dup@example.com', ShopFunction::Cashier);
        $this->post(route('workspace.staff.invite', $this->shop), ['email' => 'dup@example.com', 'staff_function' => 'manager'])->assertSessionHasErrors('business');

        $this->assertSame(1, ShopStaffInvitation::query()->count());
    }

    public function test_the_owner_changes_a_function_and_removes_a_member(): void
    {
        $cashier = $this->staffMember(ShopFunction::Cashier);
        $membership = ShopMembership::query()->where('user_id', $cashier->getKey())->sole();
        $this->assertFalse(Gate::forUser($cashier)->allows(ShopArea::Stock->ability(), $this->shop));

        $this->actingAs($this->owner)->put(route('workspace.staff.function', [$this->shop, $membership]), ['staff_function' => 'manager'])->assertRedirect();
        $this->assertTrue(Gate::forUser($cashier)->allows(ShopArea::Stock->ability(), $this->shop));

        $this->delete(route('workspace.staff.remove', [$this->shop, $membership]))->assertRedirect();
        $this->assertFalse($cashier->hasShopRole($this->shop->getKey(), RoleCode::ShopStaff));
        $this->assertSame(0, ShopStaffFunction::query()->count());
        $this->actingAs($cashier)->get(route('workspace.dashboard', $this->shop))->assertForbidden();
    }

    public function test_staff_cannot_manage_staff_the_profile_or_other_shops(): void
    {
        $manager = $this->staffMember(ShopFunction::Manager);
        $this->actingAs($manager);

        $this->get(route('workspace.staff.index', $this->shop))->assertForbidden();
        $this->get(route('workspace.profile.edit', $this->shop))->assertForbidden();
        $this->post(route('workspace.staff.invite', $this->shop), ['email' => 'a@example.com', 'staff_function' => 'cashier'])->assertForbidden();

        $otherShop = Shop::factory()->create();
        $this->get(route('workspace.dashboard', $otherShop))->assertForbidden();
        $ownerMembership = ShopMembership::query()->where('user_id', $this->owner->getKey())->sole();
        $this->actingAs($this->owner)->delete(route('workspace.staff.remove', [$this->shop, $ownerMembership]))->assertSessionHasErrors('business');
        $this->assertTrue($this->owner->hasShopRole($this->shop->getKey(), RoleCode::ShopOwner));
    }

    public function test_a_membership_of_another_shop_is_not_found_through_this_shop(): void
    {
        $otherShop = Shop::factory()->create();
        $foreign = $this->staffMember(ShopFunction::Cashier, $otherShop);
        $membership = ShopMembership::query()->where('user_id', $foreign->getKey())->sole();

        $this->actingAs($this->owner)->put(route('workspace.staff.function', [$this->shop, $membership]), ['staff_function' => 'manager'])->assertNotFound();
        $this->delete(route('workspace.staff.remove', [$this->shop, $membership]))->assertNotFound();
        $this->assertTrue($foreign->hasShopRole($otherShop->getKey(), RoleCode::ShopStaff));
    }

    public function test_the_area_matrix_follows_the_work_function(): void
    {
        $expected = [
            ShopFunction::Manager->value => ['stock', 'orders', 'promotions', 'support', 'maintenance'],
            ShopFunction::CatalogueEditor->value => ['stock'],
            ShopFunction::Cashier->value => ['orders', 'support'],
        ];

        foreach (ShopFunction::cases() as $function) {
            $user = $this->staffMember($function);
            $allowed = collect(ShopArea::cases())->filter(fn (ShopArea $area) => Gate::forUser($user)->allows($area->ability(), $this->shop))->map->value->values()->all();
            $this->assertEqualsCanonicalizing($expected[$function->value], $allowed, $function->value);
        }

        $unassigned = User::factory()->create();
        app(MembershipService::class)->grantShopRole($unassigned, $this->shop, RoleCode::ShopStaff);
        $this->assertSame([], array_values(array_filter(ShopArea::cases(), fn (ShopArea $area) => Gate::forUser($unassigned)->allows($area->ability(), $this->shop))));

        $this->assertCount(count(ShopArea::cases()), array_filter(ShopArea::cases(), fn (ShopArea $area) => Gate::forUser($this->owner)->allows($area->ability(), $this->shop)));
    }

    public function test_a_suspended_account_has_no_access(): void
    {
        $this->owner->forceFill(['status' => 'suspended'])->save();

        $this->assertFalse(Gate::forUser($this->owner)->allows(ShopArea::Stock->ability(), $this->shop));
    }
}
