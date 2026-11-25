<?php

/*
| Prints a link to the AR directions page of a mall's published map, with
| #preview so it can be walked through on a computer without a camera.
| Run from the map-service folder:
|
|   php artisan tinker ..\guide\TestByYourself\scripts\ar-preview-link.php
|
| Optional environment variables (PowerShell: $env:TO = 'unit:G-201'):
|   MALL       the mall slug            (default niagara-city-mall)
|   FROM_CODE  the QR code to start at  (default G-C02)
|   TO         the destination place    (default unit:1-105)
|   LINK_LANG  en or bn                 (default en)
|   MAP_URL    the map service address  (default http://127.0.0.1:8100)
|
| The link is signed and must be opened within 15 minutes. Read-only.
*/

use App\Models\ApiKey;
use App\Models\Checkpoint;
use App\Models\MallMap;
use App\Services\EmbedService;
use Illuminate\Support\Facades\URL;

URL::forceRootUrl(getenv('MAP_URL') ?: 'http://127.0.0.1:8100');

$mall = getenv('MALL') ?: 'niagara-city-mall';
$map = MallMap::publishedFor($mall);
if ($map === null) {
    echo "No published map for the mall {$mall}.".PHP_EOL;

    return;
}

$key = ApiKey::query()->get()->first(fn (ApiKey $key): bool => $key->isActive() && $key->can('maps:read'));
if ($key === null) {
    echo 'No active API key with maps:read. Create one: php artisan api-key:create "Smart Mall app" --ability=maps:read --origin=http://127.0.0.1:8000'.PHP_EOL;

    return;
}

$code = getenv('FROM_CODE') ?: 'G-C02';
$slug = Checkpoint::query()->where('mall_ref', $mall)->where('checkpoint_code', $code)->where('status', 'active')->value('public_slug');
if ($slug === null) {
    echo "QR code {$code} is not an active code of {$mall}; the preview starts at the first code instead.".PHP_EOL;
}

$link = app(EmbedService::class)->arLink($key, $map, [
    'to' => getenv('TO') ?: 'unit:1-105',
    'from' => $slug === null ? null : "checkpoint:{$slug}",
    'lang' => getenv('LINK_LANG') ?: 'en',
]);

echo 'AR page (open within 15 minutes): '.$link['url'].'#preview'.PHP_EOL;
