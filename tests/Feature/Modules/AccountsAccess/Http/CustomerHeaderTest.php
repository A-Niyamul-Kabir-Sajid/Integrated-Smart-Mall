<?php

namespace Tests\Feature\Modules\AccountsAccess\Http;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_offered_sign_in_and_registration(): void
    {
        $this->get(route('login'))
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertDontSee(__('Sign out'));
    }

    public function test_signed_in_users_see_their_name_and_a_sign_out_button(): void
    {
        $user = User::factory()->create(['display_name' => 'Rafiq Islam']);

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertSee('href="'.route('account.show').'"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee(__('Sign out'));
    }
}
