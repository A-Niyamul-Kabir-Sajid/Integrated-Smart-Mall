<?php

namespace Tests\Feature\Modules\AccountsAccess\Http\Controllers;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Notifications\PasswordChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['password_hash' => 'mall2026x']);
    }

    public function test_guests_cannot_change_a_password(): void
    {
        $this->get(route('account.password.edit'))->assertRedirect(route('login'));
        $this->put(route('account.password.update'), [])->assertRedirect(route('login'));
    }

    public function test_changes_the_password_with_the_correct_current_one(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('account.password.update'), ['current_password' => 'mall2026x', 'password' => 'newMall2026', 'password_confirmation' => 'newMall2026'])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status', 'Your password has been changed. Other devices were signed out.');

        $this->assertTrue(Hash::check('newMall2026', $user->fresh()->password_hash));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, PasswordChanged::class);
    }

    public function test_rejects_a_wrong_current_password_and_weak_or_reused_new_ones(): void
    {
        $user = $this->user();
        $put = fn (array $input) => $this->actingAs($user)->put(route('account.password.update'), $input);

        $put(['current_password' => 'wrong-pass1', 'password' => 'newMall2026', 'password_confirmation' => 'newMall2026'])
            ->assertSessionHasErrors(['current_password' => 'The current password is incorrect.']);
        $put(['current_password' => 'mall2026x', 'password' => 'mall2026x', 'password_confirmation' => 'mall2026x'])
            ->assertSessionHasErrors(['password' => 'Choose a password different from your current one.']);
        $put(['current_password' => 'mall2026x', 'password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('mall2026x', $user->fresh()->password_hash));
    }

    public function test_repeated_attempts_are_throttled(): void
    {
        $user = $this->user();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($user)->put(route('account.password.update'), ['current_password' => 'wrong-pass1', 'password' => 'newMall2026', 'password_confirmation' => 'newMall2026']);
        }

        $this->actingAs($user)
            ->put(route('account.password.update'), ['current_password' => 'mall2026x', 'password' => 'newMall2026', 'password_confirmation' => 'newMall2026'])
            ->assertTooManyRequests();
        $this->assertTrue(Hash::check('mall2026x', $user->fresh()->password_hash));
    }
}
