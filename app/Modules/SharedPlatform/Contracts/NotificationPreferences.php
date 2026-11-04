<?php

namespace App\Modules\SharedPlatform\Contracts;

use App\Modules\SharedPlatform\Enums\NotificationType;

/**
 * Decides which channels deliver a notification type to a notifiable. The
 * account preferences story binds an implementation that reads stored user
 * settings; until then DefaultNotificationPreferences applies.
 */
interface NotificationPreferences
{
    /**
     * @return list<string> channel names, e.g. ['database', 'mail', 'sms']
     */
    public function channelsFor(object $notifiable, NotificationType $type): array;
}
