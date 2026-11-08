<?php

namespace App\Modules\ReviewsModeration\Enums;

/**
 * MODERATION_ACTION.action: what a moderator did to a review.
 */
enum ModerationActionType: string
{
    case Publish = 'publish';
    case Hide = 'hide';
    case Restore = 'restore';
    case DismissReport = 'dismiss_report';
    case RevokeVerification = 'revoke_verification';
}
