<?php

namespace Tests\Feature\Modules\FavouritesPreferences\Http;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Models\UserPreference;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return ['locale' => 'en', 'route_mode' => 'shortest_distance', 'default_mall_id' => '', 'notify_by_email' => '1', ...$overrides];
    }

    public function test_guests_cannot_see_or_change_preferences(): void
    {
        $this->get(route('account.preferences.edit'))->assertRedirect(route('login'));
        $this->put(route('account.preferences.update'), $this->input())->assertRedirect(route('login'));
    }

    public function test_the_form_shows_the_defaults_and_active_malls_only(): void
    {
        Mall::factory()->create(['name' => 'Demo City Mall']);
        Mall::factory()->draft()->create(['name' => 'Hidden Draft Mall']);

        $this->actingAs(User::factory()->create())
            ->get(route('account.preferences.edit'))
            ->assertOk()
            ->assertSee('Demo City Mall')
            ->assertDontSee('Hidden Draft Mall')
            ->assertSee('value="shortest_distance" checked', false);
    }

    public function test_saving_stores_the_users_own_preferences_and_switches_the_language(): void
    {
        $user = User::factory()->create();
        $mall = Mall::factory()->create();

        $this->actingAs($user)
            ->put(route('account.preferences.update'), $this->input([
                'locale' => 'bn', 'route_mode' => 'accessible_distance', 'default_mall_id' => $mall->getKey(), 'notify_by_sms' => '1',
            ]))
            ->assertRedirect(route('account.preferences.edit'))
            ->assertSessionHas('locale', 'bn');

        $stored = UserPreference::query()->findOrFail($user->getKey());
        $this->assertSame(['bn', 'accessible_distance', $mall->getKey(), true, true, false], [
            $stored->locale, $stored->route_mode->value, $stored->default_mall_id, $stored->notify_by_email, $stored->notify_by_sms, $stored->ar_preferred,
        ]);
    }

    public function test_unchecked_boxes_turn_the_switch_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.preferences.update'), $this->input(['notify_by_email' => null]));

        $this->assertFalse(UserPreference::query()->findOrFail($user->getKey())->notify_by_email);
    }

    public function test_invalid_choices_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('account.preferences.update'), $this->input([
                'locale' => 'fr', 'route_mode' => 'teleport', 'default_mall_id' => Mall::factory()->draft()->create()->getKey(),
            ]))
            ->assertSessionHasErrors(['locale', 'route_mode', 'default_mall_id']);

        $this->assertDatabaseCount('user_preferences', 0);
    }

    public function test_one_user_cannot_change_another_users_preferences(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->put(route('account.preferences.update'), [...$this->input(['locale' => 'bn']), 'user_id' => $other->getKey()]);

        $this->assertNull(UserPreference::query()->find($other->getKey()));
        $this->assertSame('bn', UserPreference::query()->findOrFail($user->getKey())->locale);
    }
}
