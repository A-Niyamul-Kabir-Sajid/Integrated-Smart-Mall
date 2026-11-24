<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Import root
    |--------------------------------------------------------------------------
    |
    | The admin "Import" page lists map package folders found under this
    | folder (the Blender pipeline's work folder by default). Only folders
    | inside it can be imported from the browser.
    |
    */

    'import_root' => env('MAP_IMPORT_ROOT', base_path('../tools/blender/work')),

    /*
    |--------------------------------------------------------------------------
    | Signed file links
    |--------------------------------------------------------------------------
    |
    | Minutes a floor model, plan image or annotation image link stays valid.
    | API clients receive these links instead of needing the key in a browser.
    |
    */

    'signed_url_minutes' => (int) env('MAP_SIGNED_URL_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | API rate limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute allowed for one API key.
    |
    */

    'api_per_minute' => (int) env('MAP_API_PER_MINUTE', 120),

    /*
    |--------------------------------------------------------------------------
    | QR checkpoint address
    |--------------------------------------------------------------------------
    |
    | What a printed QR code opens: a page of the Smart Mall app that asks
    | this service where the code hangs. {mall} is the mall slug and {slug}
    | the checkpoint's random public slug.
    |
    */

    'qr_url' => env('MAP_QR_URL', 'http://127.0.0.1:8000/malls/{mall}/map/qr/{slug}'),

];
