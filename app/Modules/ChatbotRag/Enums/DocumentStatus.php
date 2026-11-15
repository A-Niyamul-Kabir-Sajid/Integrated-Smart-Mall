<?php

namespace App\Modules\ChatbotRag\Enums;

/**
 * KNOWLEDGE_DOCUMENT.status. A withdrawn document is never retrieved; it stays stored while an
 * earlier answer still cites it.
 */
enum DocumentStatus: string
{
    case Active = 'active';
    case Withdrawn = 'withdrawn';
}
