<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\View\View;

/**
 * The public API documentation at /docs: endpoints, places, routes, QR
 * checkpoints, the embedded map and its messages. It shows no keys or data.
 */
class DocsController extends Controller
{
    public function __invoke(): View
    {
        return view('docs', [
            'base' => url('/api/v1'),
            'abilities' => ApiKey::ABILITIES,
            'perMinute' => (int) config('mapservice.api_per_minute', 120),
            'linkMinutes' => (int) config('mapservice.embed_link_minutes', 15),
            'sessionMinutes' => (int) config('mapservice.embed_session_minutes', 240),
            'fileMinutes' => (int) config('mapservice.signed_url_minutes', 120),
        ]);
    }
}
