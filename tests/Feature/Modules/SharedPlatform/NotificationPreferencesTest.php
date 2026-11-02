<?php

namespace Tests\Feature\Modules\SharedPlatform;

use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use App\Modules\SharedPlatform\Enums\NotificationType;
use App\Modules\SharedPlatform\Services\DefaultNotificationPreferences;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    public function test_default_preferences_are_bound(): void
    {
        $this->assertInstanceOf(DefaultNotificationPreferences::class, $this->app->make(NotificationPreferences::class));
    }

    public function test_system_notices_are_in_app_only_by_default(): void
    {
        $this->assertSame(['database'], (new DefaultNotificationPreferences)->channelsFor(new AnonymousNotifiable, NotificationType::SystemNotice));
    }

    public function test_account_security_messages_are_also_emailed_by_default(): void
    {
        $this->assertSame(['database', 'mail'], (new DefaultNotificationPreferences)->channelsFor(new AnonymousNotifiable, NotificationType::AccountSecurity));
    }
}
