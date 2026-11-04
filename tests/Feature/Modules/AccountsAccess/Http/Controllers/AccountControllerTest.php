<?php

namespace Tests\Feature\Modules\AccountsAccess\Http\Controllers;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_signed_in_users_own_details(): void
    {
        $user = User::factory()->create(['display_name' => 'Rafiq Islam', 'email' => 'rafiq@example.com', 'phone' => '01812345678']);
        User::factory()->create(['display_name' => 'Someone Else']);

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSeeInOrder(['Rafiq Islam', 'rafiq@example.com', '+8801812345678'])
            ->assertDontSee('Someone Else');
    }
}
