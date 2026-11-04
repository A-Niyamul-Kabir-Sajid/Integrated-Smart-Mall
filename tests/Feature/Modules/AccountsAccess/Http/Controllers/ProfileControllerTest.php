<?php

namespace Tests\Feature\Modules\AccountsAccess\Http\Controllers;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create([
            'display_name' => 'Rafiq Islam',
            'email' => 'rafiq@example.com',
            'phone' => '+8801712345678',
            'password_hash' => 'mall2026x',
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function input(array $overrides = []): array
    {
        return ['display_name' => 'Rafiq Islam', 'email' => 'rafiq@example.com', 'phone' => '01712345678', 'current_password' => '', ...$overrides];
    }

    public function test_guests_cannot_view_or_change_a_profile(): void
    {
        $this->get(route('account.profile.edit'))->assertRedirect(route('login'));
        $this->put(route('account.profile.update'), $this->input())->assertRedirect(route('login'));
    }

    public function test_the_form_is_prefilled_with_the_users_own_details(): void
    {
        $this->actingAs($this->owner())
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee('value="Rafiq Islam"', false)
            ->assertSee('value="rafiq@example.com"', false);
    }

    public function test_changing_the_name_needs_no_password_and_records_only_the_field(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->put(route('account.profile.update'), $this->input(['display_name' => 'Rafiq Hasan']))
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status', 'Your profile has been updated.');

        $this->assertSame('Rafiq Hasan', $user->fresh()->display_name);
        $this->assertSame(['fields' => ['display_name']], AuditLog::query()->sole()->metadata);
    }

    public function test_changing_a_contact_requires_the_current_password(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->put(route('account.profile.update'), $this->input(['email' => 'new@example.com']))
            ->assertSessionHasErrors(['current_password' => 'Enter your current password to change your email or mobile number.']);

        $this->actingAs($user)
            ->put(route('account.profile.update'), $this->input(['email' => 'new@example.com', 'current_password' => 'wrong-pass1']))
            ->assertSessionHasErrors(['current_password' => 'The current password is incorrect.']);

        $this->assertSame('rafiq@example.com', $user->fresh()->email);
    }

    public function test_a_changed_phone_is_saved_normalised_and_loses_its_verification(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->put(route('account.profile.update'), $this->input(['phone' => '01812-345678', 'current_password' => 'mall2026x']))
            ->assertRedirect(route('account.show'));

        $this->assertSame('+8801812345678', $user->fresh()->phone);
        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_another_accounts_email_cannot_be_taken(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->owner())
            ->put(route('account.profile.update'), $this->input(['email' => 'Taken@Example.com', 'current_password' => 'mall2026x']))
            ->assertSessionHasErrors(['email' => 'Another account already uses this email.']);
    }

    public function test_both_contacts_cannot_be_removed(): void
    {
        $this->actingAs($this->owner())
            ->put(route('account.profile.update'), $this->input(['email' => '', 'phone' => '', 'current_password' => 'mall2026x']))
            ->assertSessionHasErrors(['email' => 'Keep an email address or a mobile number on your account.']);
    }

    public function test_extra_fields_cannot_change_another_account_or_the_status(): void
    {
        $user = $this->owner();
        $other = User::factory()->create(['display_name' => 'Someone Else']);

        $this->actingAs($user)->put(route('account.profile.update'), [
            ...$this->input(['display_name' => 'Changed']),
            'user_id' => $other->getKey(),
            'status' => 'suspended',
        ]);

        $this->assertSame('Someone Else', $other->fresh()->display_name);
        $this->assertSame('Changed', $user->fresh()->display_name);
        $this->assertTrue($user->fresh()->isActive());
    }
}
