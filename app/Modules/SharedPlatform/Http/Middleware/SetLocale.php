<?php

namespace App\Modules\SharedPlatform\Http\Middleware;

use App\Modules\SharedPlatform\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language chosen for this session (English by default).
 */
class SetLocale
{
    public function __construct(private LocaleService $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->locales->fromSession($request->session());

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
