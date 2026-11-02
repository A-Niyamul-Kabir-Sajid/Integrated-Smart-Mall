<?php

namespace Tests\Feature\Modules\FavouritesPreferences;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\FavouritesPreferences\Services\PreferenceService;
use App\Modules\FavouritesPreferences\Services\UserNotificationPreferences;
use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use App\Modules\SharedPlatform\Enums\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class PreferenceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function channels(User $user, NotificationType $type = NotificationType::SystemNotice): array
    {
        return app(NotificationPreferences::class)->channelsFor($user, $type);
    }

    public function test_user_preferences_replace_the_platform_defaults(): void
    {
        $this->assertInstanceOf(UserNotificationPreferences::class, app(NotificationPreferences::class));
    }

    public function test_channels_follow_the_users_switches_and_contacts(): void
    {
        $user = User::factory()->create(['phone' => '+8801712345678']);
        $this->assertSame(['database', 'mail'], $this->channels($user));

        app(PreferenceService::class)->update($user->getKey(), ['notify_by_email' => false, 'notify_by_sms' => true]);

        $this->assertSame(['database', 'sms'], $this->channels($user));
    }

    public function test_account_security_messages_are_always_emailed(): void
    {
        $user = User::factory()->create();
        app(PreferenceService::class)->update($user->getKey(), ['notify_by_email' => false]);

        $this->assertSame(['database', 'mail'], $this->channels($user, NotificationType::AccountSecurity));
    }

    public function test_missing_contacts_are_never_used_and_other_notifiables_get_defaults(): void
    {
        $phoneOnly = User::factory()->phoneOnly()->create();

        $this->assertSame(['database'], $this->channels($phoneOnly));
        $this->assertSame(['database'], app(NotificationPreferences::class)->channelsFor(new AnonymousNotifiable, NotificationType::SystemNotice));
    }

    public function test_signing_in_switches_to_the_preferred_language(): void
    {
        $user = User::factory()->create(['email' => 'nusrat@example.com', 'password_hash' => 'mall2026x']);
        app(PreferenceService::class)->update($user->getKey(), ['locale' => 'bn']);

        $this->post(route('login.store'), ['login' => 'nusrat@example.com', 'password' => 'mall2026x'])
            ->assertSessionHas('locale', 'bn');
    }
}
