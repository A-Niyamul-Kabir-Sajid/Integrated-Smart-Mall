<?php

namespace App\Modules\ChatbotRag\Enums;

/**
 * Who a knowledge document may be quoted to, and the kind of conversation it is quoted in
 * (ERD KNOWLEDGE_DOCUMENT.access_scope and CONVERSATION.access_scope).
 */
enum AccessScope: string
{
    /** Every visitor of the mall, signed in or not. */
    case Public = 'public';

    /** Administrators of the mall only; never part of a customer answer or of a model's context. */
    case MallAdmin = 'mall_admin';

    /**
     * The document scopes a conversation of this scope may read.
     *
     * @return list<string>
     */
    public function readableScopes(): array
    {
        return match ($this) {
            self::Public => [self::Public->value],
            self::MallAdmin => [self::Public->value, self::MallAdmin->value],
        };
    }

    /**
     * The English label, translated in views with __().
     */
    public function label(): string
    {
        return match ($this) {
            self::Public => 'Everyone',
            self::MallAdmin => 'Mall administrators only',
        };
    }
}
