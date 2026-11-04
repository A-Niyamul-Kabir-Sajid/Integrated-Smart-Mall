<?php

namespace Tests\Feature\Modules\AccountsAccess\Http\Controllers;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SENT = 'If an account uses that email, we have sent a link to reset its password.';

    private function user(): User
    {
        return User::factory()->create(['email' => 'rafiq@example.com', 'password_hash' => 'mall2026x']);
    }

    public function test_sign_in_page_links_to_the_reset_form(): void
    {
        $this->get(route('login'))->assertSee('href="'.route('password.request').'"', false);
        $this->get(route('password.request'))->assertOk();
    }

    public function test_a_known_email_receives_a_reset_link(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post(route('password.email'), ['email' => 'Rafiq@Example.com'])->assertSessionHas('status', self::SENT);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_email_gets_the_same_answer_and_nothing_is_sent(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status', self::SENT);

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_resets_the_password_once(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);
        $input = ['token' => $token, 'email' => 'rafiq@example.com', 'password' => 'newMall2026', 'password_confirmation' => 'newMall2026'];

        $this->post(route('password.update'), $input)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your password has been reset. Sign in with your new password.');

        $this->assertTrue(Auth::validate(['email' => 'rafiq@example.com', 'password' => 'newMall2026']));
        $this->assertFalse(Auth::validate(['email' => 'rafiq@example.com', 'password' => 'mall2026x']));

        $this->post(route('password.update'), [...$input, 'password' => 'otherMall2026', 'password_confirmation' => 'otherMall2026'])
            ->assertSessionHasErrors(['email' => 'This password reset link is invalid or has expired.']);
    }

    public function test_invalid_tokens_and_tokens_for_another_account_are_rejected(): void
    {
        $user = $this->user();
        $other = User::factory()->create(['email' => 'other@example.com']);
        $tokenForOther = Password::createToken($other);

        foreach ([['made-up-token', 'rafiq@example.com'], [$tokenForOther, 'rafiq@example.com']] as [$token, $email]) {
            $this->post(route('password.update'), ['token' => $token, 'email' => $email, 'password' => 'newMall2026', 'password_confirmation' => 'newMall2026'])
                ->assertSessionHasErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        $this->assertTrue(Auth::validate(['email' => $user->email, 'password' => 'mall2026x']));
    }

    public function test_reset_requests_are_throttled(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post(route('password.email'), ['email' => 'nobody@example.com']);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertTooManyRequests();
    }
}
