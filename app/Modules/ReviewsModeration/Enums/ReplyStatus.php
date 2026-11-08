<?php

namespace App\Modules\ReviewsModeration\Enums;

/**
 * REVIEW_REPLY.status: a shop reply is public until the shop or a moderator hides it.
 */
enum ReplyStatus: string
{
    case Published = 'published';
    case Hidden = 'hidden';
}
