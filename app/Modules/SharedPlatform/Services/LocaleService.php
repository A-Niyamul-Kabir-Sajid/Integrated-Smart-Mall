<?php

namespace App\Modules\SharedPlatform\Services;

use Illuminate\Contracts\Session\Session;

/**
 * The interface languages and where the visitor's choice is kept. Modules use
 * this service instead of SharedPlatform's HTTP layer.
 */
class LocaleService
{
    /**
     * @var list<string>
     */
    public const SUPPORTED = ['en', 'bn'];

    public const SESSION_KEY = 'locale';

    public function isSupported(?string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    /**
     * Remembers a supported locale for the rest of the session; others are ignored.
     */
    public function remember(Session $session, ?string $locale): void
    {
        if ($this->isSupported($locale)) {
            $session->put(self::SESSION_KEY, $locale);
        }
    }

    public function fromSession(Session $session): ?string
    {
        $locale = $session->get(self::SESSION_KEY);

        return $this->isSupported($locale) ? $locale : null;
    }
}
