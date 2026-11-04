<?php

namespace App\Modules\SharedPlatform\Services;

use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use App\Modules\SharedPlatform\Enums\NotificationType;

/**
 * Platform defaults: everything appears in the in-app notification list;
 * account security messages are also emailed.
 */
class DefaultNotificationPreferences implements NotificationPreferences
{
    /**
     * @return list<string>
     */
    public function channelsFor(object $notifiable, NotificationType $type): array
    {
        return match ($type) {
            NotificationType::SystemNotice => ['database'],
            NotificationType::AccountSecurity => ['database', 'mail'],
        };
    }
}
