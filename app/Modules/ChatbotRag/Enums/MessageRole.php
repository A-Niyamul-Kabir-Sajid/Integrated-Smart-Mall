<?php

namespace App\Modules\ChatbotRag\Enums;

/**
 * CHAT_MESSAGE.role. Only user and assistant messages are stored and shown; instructions given to
 * a model are never written to a conversation.
 */
enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';
}
