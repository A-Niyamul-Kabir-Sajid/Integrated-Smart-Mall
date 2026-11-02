<?php

namespace App\Modules\AccountsAccess\Enums;

/**
 * APP_USER.status. Only active accounts may sign in; anonymised accounts keep
 * their id for history but hold no personal data.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Anonymized = 'anonymized';
}
