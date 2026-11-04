<?php

namespace Tests\Feature\Modules\AccountsAccess\Http\Controllers;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_sign_in_form(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('name="login"', false);
    }

    public function test_signed_in_users_skip_the_sign_in_form(): void
    {
        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect('/account');
    }

    public function test_signs_in_with_an_email_in_any_letter_case(): void
    {
        $user = User::factory()->create(['email' => 'rafiq@example.com', 'password_hash' => 'mall2026x']);

        $this->post(route('login.store'), ['login' => ' Rafiq@Example.com ', 'password' => 'mall2026x'])
            ->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_signs_in_with_a_mobile_number_in_local_format(): void
    {
        $user = User::factory()->phoneOnly('+8801712345678')->create(['password_hash' => 'mall2026x']);

        $this->post(route('login.store'), ['login' => '01712 345678', 'password' => 'mall2026x'])
            ->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_returns_to_the_page_the_guest_originally_asked_for(): void
    {
        User::factory()->create(['email' => 'rafiq@example.com', 'password_hash' => 'mall2026x']);

        $this->get(route('account.show'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['login' => 'rafiq@example.com', 'password' => 'mall2026x'])
            ->assertRedirect(route('account.show'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rejectedSignIns(): array
    {
        return [
            'active account, wrong password' => ['active', 'wrong-password1'],
            'suspended account, right password' => ['suspended', 'mall2026x'],
            'anonymised account, right password' => ['anonymized', 'mall2026x'],
        ];
    }

    #[DataProvider('rejectedSignIns')]
    public function test_failed_sign_in_shows_one_generic_message_and_keeps_the_login(string $status, string $password): void
    {
        User::factory()->create(['email' => 'rafiq@example.com', 'password_hash' => 'mall2026x', 'status' => $status]);

        $this->from(route('login'))
            ->post(route('login.store'), ['login' => 'rafiq@example.com', 'password' => $password])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login' => 'These credentials do not match our records.'])
            ->assertSessionHasInput('login', 'rafiq@example.com')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_keep_me_signed_in_sets_the_remember_cookie(): void
    {
        User::factory()->create(['email' => 'rafiq@example.com', 'password_hash' => 'mall2026x']);

        $this->post(route('login.store'), ['login' => 'rafiq@example.com', 'password' => 'mall2026x', 'remember' => '1'])
            ->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_the_sixth_attempt_for_the_same_login_within_a_minute_is_throttled(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['login' => 'rafiq@example.com', 'password' => 'wrong-password1']);
        }

        $this->post(route('login.store'), ['login' => 'rafiq@example.com', 'password' => 'wrong-password1'])
            ->assertTooManyRequests();
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect('/')
            ->assertSessionHas('status', 'You have been signed out.');

        $this->assertGuest();
    }

    public function test_guests_cannot_sign_out(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
}
