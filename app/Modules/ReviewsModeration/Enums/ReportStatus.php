<?php

namespace App\Modules\ReviewsModeration\Enums;

/**
 * REVIEW_REPORT.status: open until a moderator resolves it (action taken) or dismisses it.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
