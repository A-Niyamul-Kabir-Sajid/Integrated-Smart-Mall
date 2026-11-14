<?php

namespace App\Modules\AnalyticsReports\Services;

/**
 * Recognises requests that are not a person using a browser. A browser always sends a user agent, so a
 * request without one is treated as automated too.
 */
class CrawlerDetector
{
    private const PATTERN = '/bot|crawl|spider|slurp|preview|headless|lighthouse|monitor|uptime|curl|wget|python-requests|httpclient|go-http-client|java\//i';

    public function isCrawler(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent === '' || preg_match(self::PATTERN, $userAgent) === 1;
    }
}
