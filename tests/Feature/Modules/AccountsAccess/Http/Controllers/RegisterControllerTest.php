<?php

namespace Tests\Feature\Modules\AccountsAccess\Http\Controllers;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return [
            'display_name' => 'Nusrat Jahan',
            'email' => 'nusrat@example.com',
            'phone' => '',
            'password' => 'mall2026x',
            'password_confirmation' => 'mall2026x',
            ...$overrides,
        ];
    }

    public function test_guests_see_the_registration_form(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee(__('Create an account'))
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_signed_in_users_are_sent_to_their_account_instead(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('register'))
            ->assertRedirect('/account');
    }

    public function test_registering_with_an_email_creates_a_signed_in_customer(): void
    {
        $response = $this->post(route('register.store'), $this->validInput(['email' => ' Nusrat@Example.COM ']));

        $user = User::query()->sole();
        $response->assertRedirect(route('account.show'))
            ->assertSessionHas('status', 'Welcome, Nusrat Jahan! Your account is ready.');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('nusrat@example.com', $user->email);
        $this->assertTrue($user->hasPlatformRole(RoleCode::Customer));
    }

    public function test_registering_with_only_a_local_mobile_number_stores_it_in_e164(): void
    {
        $this->post(route('register.store'), $this->validInput(['email' => '', 'phone' => '01712-345678']))
            ->assertRedirect(route('account.show'));

        $this->assertSame('+8801712345678', User::query()->sole()->phone);
    }

    public function test_an_email_or_a_mobile_number_is_required(): void
    {
        $this->post(route('register.store'), $this->validInput(['email' => '', 'phone' => '']))
            ->assertSessionHasErrors(['email' => 'Enter an email address or a mobile number.']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_email_already_registered_in_any_letter_case_is_rejected(): void
    {
        User::factory()->create(['email' => 'nusrat@example.com']);

        $this->post(route('register.store'), $this->validInput(['email' => 'NUSRAT@example.com']))
            ->assertSessionHasErrors(['email' => 'An account with this email already exists.']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_mobile_number_already_registered_is_rejected(): void
    {
        User::factory()->phoneOnly('+8801712345678')->create();

        $this->post(route('register.store'), $this->validInput(['email' => '', 'phone' => '01712345678']))
            ->assertSessionHasErrors(['phone' => 'An account with this mobile number already exists.']);
    }

    public function test_an_invalid_mobile_number_is_rejected(): void
    {
        $this->post(route('register.store'), $this->validInput(['email' => '', 'phone' => '12345']))
            ->assertSessionHasErrors(['phone' => 'Enter a valid mobile number, for example 01712345678.']);
    }

    public function test_the_password_must_be_confirmed_and_strong(): void
    {
        $this->post(route('register.store'), $this->validInput(['password_confirmation' => 'different1']))
            ->assertSessionHasErrors(['password' => 'The password field confirmation does not match.']);

        $this->post(route('register.store'), $this->validInput(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters']))
            ->assertSessionHasErrors(['password' => 'The password field must contain at least one number.']);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_the_eleventh_attempt_within_an_hour_is_throttled(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->post(route('register.store'), $this->validInput(['email' => '', 'phone' => '']));
        }

        $this->post(route('register.store'), $this->validInput())->assertTooManyRequests();
    }

    public function test_guests_cannot_open_the_account_page(): void
    {
        $this->get(route('account.show'))->assertRedirect('/register');
    }
}
