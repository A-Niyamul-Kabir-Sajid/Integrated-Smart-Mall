<?php

namespace App\Modules\ChatbotRag\Exceptions;

use RuntimeException;

/**
 * A model driver could not produce an answer. The reason is a short code that is safe to log and
 * store: it never contains the API key, the question or the model's reply.
 */
class GenerationUnavailable extends RuntimeException
{
    public const MISSING_KEY = 'missing_key';

    public const RATE_LIMITED = 'rate_limited';

    public const API_ERROR = 'api_error';

    public const CONNECTION = 'connection_failed';

    public const NO_OUTPUT = 'no_output';

    public const MALFORMED = 'malformed_output';

    public const RESTRICTED_CONTEXT = 'restricted_context';

    /**
     * @param  array<string, scalar|null>  $context  Diagnostic values safe to log, such as the HTTP status
     */
    public function __construct(public readonly string $reason, public readonly array $context = [])
    {
        parent::__construct("The answer generator is unavailable: {$reason}.");
    }
}
