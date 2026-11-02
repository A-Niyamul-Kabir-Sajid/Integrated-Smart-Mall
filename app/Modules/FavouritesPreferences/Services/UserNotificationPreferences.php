<?php

namespace App\Modules\FavouritesPreferences\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\SharedPlatform\Contracts\NotificationPreferences;
use App\Modules\SharedPlatform\Enums\NotificationType;
use App\Modules\SharedPlatform\Services\DefaultNotificationPreferences;

/**
 * Channels chosen by each user. Everything stays in the in-app list; email and
 * SMS follow the user's switches when a contact exists. Account security
 * messages always go to the email address, whatever the switches say.
 */
class UserNotificationPreferences implements NotificationPreferences
{
    public function __construct(
        private PreferenceService $preferences,
        private DefaultNotificationPreferences $defaults,
    ) {}

    /**
     * @return list<string>
     */
    public function channelsFor(object $notifiable, NotificationType $type): array
    {
        if (! $notifiable instanceof User) {
            return $this->defaults->channelsFor($notifiable, $type);
        }

        $preference = $this->preferences->forUser($notifiable->getKey());
        $isSecurity = $type === NotificationType::AccountSecurity;
        $channels = ['database'];

        if ($notifiable->email !== null && ($isSecurity || $preference->notify_by_email)) {
            $channels[] = 'mail';
        }

        if ($notifiable->phone !== null && $preference->notify_by_sms) {
            $channels[] = 'sms';
        }

        return $channels;
    }
}
