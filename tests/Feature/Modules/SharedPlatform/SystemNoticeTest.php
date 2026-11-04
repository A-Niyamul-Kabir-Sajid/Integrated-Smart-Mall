<?php

namespace Tests\Feature\Modules\SharedPlatform;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use App\Modules\SharedPlatform\Enums\NotificationType;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SystemNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_is_stored_in_the_users_in_app_notifications(): void
    {
        $user = User::factory()->create();

        $user->notify(new SystemNotice('Early closing', 'The mall closes at 8 pm on Friday.', 'https://example.test/notices/1'));

        $this->assertDatabaseHas('notifications', [
            'type' => NotificationType::SystemNotice->value,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
            'read_at' => null,
        ]);
        $this->assertSame([
            'title' => 'Early closing',
            'body' => 'The mall closes at 8 pm on Friday.',
            'action_url' => 'https://example.test/notices/1',
        ], $user->unreadNotifications()->sole()->data);
    }

    public function test_channels_follow_the_bound_notification_preferences(): void
    {
        Notification::fake();
        $this->app->instance(NotificationPreferences::class, new class implements NotificationPreferences
        {
            public function channelsFor(object $notifiable, NotificationType $type): array
            {
                return ['database', 'mail'];
            }
        });
        $user = User::factory()->create();

        $user->notify(new SystemNotice('Title', 'Body'));

        Notification::assertSentTo($user, SystemNotice::class, fn (SystemNotice $notice, array $channels): bool => $channels === ['database', 'mail']);
    }

    public function test_mail_version_links_to_the_action_only_when_one_is_given(): void
    {
        $user = User::factory()->make();

        $this->assertSame('Open', (new SystemNotice('Title', 'Body', 'https://example.test'))->toMail($user)->actionText);
        $this->assertNull((new SystemNotice('Title', 'Body'))->toMail($user)->actionText);
    }
}
