<?php

namespace App\Modules\ChatbotRag\Services\Assistant;

/**
 * Looks at a message before anything else does and names a reason to refuse it: an attempt to
 * change the assistant's rules (prompt injection), or a request for other people's data or secrets.
 *
 * This is a courtesy, not the protection. What a conversation may read and do is decided by the
 * access scope and the tool allowlist, which no wording can change; the guard only spares the
 * visitor a confusing answer and gives administrators a count of such attempts.
 */
class InputGuard
{
    public const PROMPT_INJECTION = 'prompt_injection';

    public const PRIVATE_DATA = 'private_data';

    private const INJECTION = [
        '/\b(ignore|disregard|forget|override|bypass)\b[^.?!\n]{0,30}\b(your|all|any|previous|prior|above|earlier|these|those|system)\b[^.?!\n]{0,20}\b(instruction|instructions|rule|rules|prompt|prompts|guideline|guidelines|restriction|restrictions)\b/iu',
        '/\b(ignore|disregard|forget)\s+(everything|all)\s+(above|before|previous|prior|you were told|you have been told)\b/iu',
        '/\b(system|developer|hidden|initial|original)\s+(prompt|message|instruction|instructions)\b/iu',
        '/\b(reveal|show|print|repeat|display|leak|tell me|what (is|are))\b[^.?!\n]{0,30}\byour\s+(system\s+)?(prompt|instruction|instructions|configuration|guidelines)\b/iu',
        '/\b(you are now|from now on,? you|act as (an? |the )?(admin|administrator|developer|system|root|owner|manager|dan)|pretend (to be|you are)|role-?play as|simulate being)\b/iu',
        '/\b(developer mode|jailbreak|jailbroken|dan mode|do anything now|god mode|sudo mode|admin mode|unrestricted mode)\b/iu',
        '/\bnew (instruction|instructions|rule|rules)\s*:/iu',
        '/<\/?\s*(system|assistant|instruction|instructions|prompt)\s*>|\[\/?(INST|SYS|SYSTEM)\]|^\s*#{2,}\s*(system|instruction)/imu',
        '/(আগের|পূর্বের|উপরের|সব)\s+(নির্দেশ|নির্দেশনা|নিয়ম)[^।?!\n]{0,30}(ভুলে|উপেক্ষা|বাদ)/u',
        '/সিস্টেম\s+প্রম্পট/u',
    ];

    private const PRIVATE = [
        '/\b(password|passwords|passcode|api key|api keys|secret key|access token|credit card|card number|cvv|database dump|sql dump)\b/iu',
        '/\b(list|show|give|export|dump|send)\b[^.?!\n]{0,25}\b(users|customers|accounts|members|email addresses|emails|phone numbers)\b/iu',
        '/\b(another|other|someone else\'?s?|somebody else\'?s?|a different)\s+(customer|user|person|account)\b[^.?!\n]{0,30}\b(order|orders|purchase|purchases|address|phone|email|review|receipt|parking)\b/iu',
        '/\bwho (bought|purchased|ordered|reviewed|complained|parked)\b/iu',
    ];

    /**
     * @return string|null The reason to refuse the message, or null to let it through
     */
    public function inspect(string $message): ?string
    {
        if ($this->containsInstructions($message)) {
            return self::PROMPT_INJECTION;
        }

        foreach (self::PRIVATE as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return self::PRIVATE_DATA;
            }
        }

        return null;
    }

    /**
     * Whether a text is worded like an instruction to the assistant. Also used to warn an
     * administrator who saves such a text as a knowledge document.
     */
    public function containsInstructions(string $text): bool
    {
        foreach (self::INJECTION as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
