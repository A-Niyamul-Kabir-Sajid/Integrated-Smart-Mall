<?php

namespace Tests\Feature\Modules\FavouritesPreferences;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Contracts\PreferenceLookup;
use App\Modules\FavouritesPreferences\Enums\RouteMode;
use App\Modules\FavouritesPreferences\Services\PreferenceService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferenceLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_accounts_without_preferences_get_the_defaults(): void
    {
        $lookup = app(PreferenceLookup::class);

        foreach ([null, User::factory()->create()->getKey()] as $userId) {
            $preferences = $lookup->preferencesFor($userId);

            $this->assertSame('en', $preferences->locale);
            $this->assertSame(RouteMode::ShortestDistance, $preferences->routeMode);
            $this->assertNull($preferences->defaultMallId);
            $this->assertFalse($preferences->needsStepFreeRoutes());
        }
    }

    public function test_stored_preferences_are_returned_for_their_account_only(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        $mall = Mall::factory()->create();
        app(PreferenceService::class)->update($me->getKey(), ['locale' => 'bn', 'route_mode' => 'accessible_distance', 'default_mall_id' => $mall->getKey()]);

        $mine = app(PreferenceLookup::class)->preferencesFor($me->getKey());
        $theirs = app(PreferenceLookup::class)->preferencesFor($other->getKey());

        $this->assertSame('bn', $mine->locale);
        $this->assertTrue($mine->needsStepFreeRoutes());
        $this->assertSame($mall->getKey(), $mine->defaultMallId);
        $this->assertSame('en', $theirs->locale);
        $this->assertNull($theirs->defaultMallId);
    }

    public function test_the_usual_mall_is_listed_first_and_marked(): void
    {
        $user = User::factory()->create();
        Mall::factory()->create(['name' => 'Alpha Mall']);
        $usual = Mall::factory()->create(['name' => 'Zeta Mall']);
        app(PreferenceService::class)->update($user->getKey(), ['default_mall_id' => $usual->getKey()]);

        $this->get(route('home'))->assertSeeInOrder(['Alpha Mall', 'Zeta Mall'])->assertDontSee('Your usual mall');

        $this->actingAs($user)->get(route('home'))->assertSeeInOrder(['Zeta Mall', 'Your usual mall', 'Alpha Mall']);
    }

    public function test_a_bangla_visitor_gets_bangla_validation_and_sign_in_messages(): void
    {
        $this->withSession(['locale' => 'bn'])
            ->post(route('login.store'), ['login' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['login' => 'এই তথ্যের সাথে কোনো অ্যাকাউন্ট মেলেনি।']);

        $this->withSession(['locale' => 'bn'])
            ->post(route('register.store'), ['display_name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors([
                'display_name' => 'পুরো নাম দেওয়া আবশ্যক।',
                'email' => 'সঠিক ইমেইল ঠিকানা দিন।',
            ]);
    }

    public function test_english_messages_are_unchanged(): void
    {
        $this->post(route('register.store'), ['display_name' => ''])
            ->assertSessionHasErrors(['display_name' => 'The display name field is required.']);
    }
}
