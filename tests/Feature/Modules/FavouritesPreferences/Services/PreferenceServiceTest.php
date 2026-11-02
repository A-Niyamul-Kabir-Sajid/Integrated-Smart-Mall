<?php

namespace Tests\Feature\Modules\FavouritesPreferences\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Enums\RouteMode;
use App\Modules\FavouritesPreferences\Models\UserPreference;
use App\Modules\FavouritesPreferences\Services\PreferenceService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_without_a_stored_row_get_the_defaults(): void
    {
        $preference = app(PreferenceService::class)->forUser(User::factory()->create()->getKey());

        $this->assertFalse($preference->exists);
        $this->assertSame('en', $preference->locale);
        $this->assertSame(RouteMode::ShortestDistance, $preference->route_mode);
        $this->assertTrue($preference->notify_by_email);
        $this->assertFalse($preference->notify_by_sms);
        $this->assertDatabaseCount('user_preferences', 0);
    }

    public function test_the_first_change_stores_the_row_and_audits_the_changed_fields(): void
    {
        $user = User::factory()->create();
        $mall = Mall::factory()->create();

        app(PreferenceService::class)->update($user->getKey(), [
            'locale' => 'bn',
            'route_mode' => 'accessible_distance',
            'default_mall_id' => $mall->getKey(),
            'user_id' => 'someone-else',
        ]);

        $stored = UserPreference::query()->findOrFail($user->getKey());
        $this->assertSame('bn', $stored->locale);
        $this->assertSame(RouteMode::AccessibleDistance, $stored->route_mode);
        $this->assertTrue($stored->defaultMall->is($mall));
        $this->assertEqualsCanonicalizing(['locale', 'route_mode', 'default_mall_id'], AuditLog::query()->sole()->metadata['fields']);
    }

    public function test_saving_unchanged_preferences_writes_nothing(): void
    {
        $user = User::factory()->create();
        $service = app(PreferenceService::class);
        $service->update($user->getKey(), ['locale' => 'bn']);

        $service->update($user->getKey(), ['locale' => 'bn']);

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_preferences_are_removed_with_the_account(): void
    {
        $user = User::factory()->create();
        app(PreferenceService::class)->update($user->getKey(), ['locale' => 'bn']);

        $user->delete();

        $this->assertDatabaseCount('user_preferences', 0);
    }
}
