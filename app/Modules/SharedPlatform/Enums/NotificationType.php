<?php

namespace App\Modules\SharedPlatform\Enums;

/**
 * Stable identifiers of the notifications the platform sends. The value is stored
 * in notifications.type and used as the key for user channel preferences.
 */
enum NotificationType: string
{
    case SystemNotice = 'system_notice';
    case AccountSecurity = 'account_security';
}
