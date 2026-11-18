<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Answer generation (decision D-018)
    |--------------------------------------------------------------------------
    |
    | "auto" uses the Gemini driver when services.gemini.key is set and the
    | built-in template driver otherwise. "template" never calls a model.
    | Whatever is chosen, the template driver answers when the key is missing,
    | the API fails or its rate limit is hit. After a rate-limit answer the
    | model is left alone for cooldown_seconds.
    |
    */

    'driver' => env('ASSISTANT_DRIVER', 'auto'),

    'cooldown_seconds' => (int) env('ASSISTANT_COOLDOWN_SECONDS', 60),

    'max_answer_characters' => 1200,

    /*
    |--------------------------------------------------------------------------
    | Questions and conversations
    |--------------------------------------------------------------------------
    |
    | A conversation is deleted retention_days after it started: guests after
    | one day, signed-in visitors and administrators after thirty. The log of
    | what was asked (no account, no conversation) is kept interaction_days.
    |
    */

    'max_message_length' => 500,

    'history_limit' => 30,

    'retention_days' => [
        'guest' => (int) env('ASSISTANT_GUEST_RETENTION_DAYS', 1),
        'account' => (int) env('ASSISTANT_ACCOUNT_RETENTION_DAYS', 30),
    ],

    'interaction_days' => (int) env('ASSISTANT_INTERACTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Knowledge documents and retrieval
    |--------------------------------------------------------------------------
    |
    | A document is cut into chunks of about chunk_target characters, never
    | above chunk_max, and each chunk repeats the last sentence of the one
    | before. A question is answered when the best chunk covers at least
    | answer_coverage of its words, answered with a warning from low_coverage,
    | and not answered below that.
    |
    */

    'max_document_characters' => 60000,

    'chunk_target' => 700,

    'chunk_max' => 1000,

    'retrieval' => [
        'candidates' => 40,
        'top' => 4,
        'answer_coverage' => 0.6,
        'low_coverage' => 0.34,
    ],

    'max_items' => 5,

];
