<!doctype html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0b1220">
    <title>{{ $map->name }} · AR</title>
    <link rel="stylesheet" href="{{ asset('ar/ar.css') }}?v=1">
</head>
<body>
<main id="intro" class="intro" aria-busy="true">
    <p class="eyebrow" data-t="title">AR directions</p>
    <h1 id="destination">{{ $map->name }}</h1>
    <p id="trip" class="trip"></p>
    <p id="status" class="status" role="status" aria-live="polite" data-t="checking">Checking this phone…</p>
    <p class="actions">
        <button type="button" id="start" class="primary" hidden data-t="start">Start AR</button>
        <button type="button" id="preview" class="secondary" hidden data-t="preview">Preview without a camera</button>
    </p>
    <ol class="how">
        <li data-t="how1">Move the phone slowly over the floor until a ring appears.</li>
        <li data-t="how2">Point the camera at a QR code of the mall from about half a metre, or hold the phone flat against the code and tap Align.</li>
        <li data-t="how3">Follow the arrows on the floor. Line up again at any QR code if the arrows drift.</li>
    </ol>
    <p class="small" data-t="needs">AR needs Chrome on an Android phone with Google Play Services for AR. On other phones, use the map.</p>
    <p><button type="button" id="close" class="link" data-t="back">Back to the map</button></p>
</main>

<div id="overlay" class="overlay" hidden>
    <div class="top">
        <div class="instruction"><span id="icon" class="icon" aria-hidden="true"></span><span id="say"></span></div>
        <div id="meta" class="meta"></div>
    </div>
    <div id="hint" class="hint" hidden></div>
    <div id="aim" class="aim" hidden aria-hidden="true"></div>
    <div id="banner" class="banner" hidden>
        <p id="banner-text"></p>
        <button type="button" id="banner-action"></button>
    </div>
    <div id="panel" class="panel">
        <p id="panel-text"></p>
        <div id="touch-row" class="row" hidden>
            <label for="code" class="sr" data-t="whichCode">QR code</label>
            <select id="code"></select>
            <button type="button" id="touch" class="primary" data-t="align">Align</button>
        </div>
        <div id="step-row" class="row" hidden>
            <button type="button" id="skip" hidden data-t="skip">Skip</button>
            <button type="button" id="keep" hidden></button>
        </div>
    </div>
    <div class="bottom">
        <button type="button" id="realign" data-t="realign">Re-align</button>
        <button type="button" id="details-button" data-t="details">Details</button>
        <button type="button" id="exit" data-t="exit">Exit</button>
    </div>
    <pre id="details" class="details" hidden></pre>
    <div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
</div>

<script type="application/json" id="ar-config">@json($config)</script>
<script type="module" src="{{ asset('ar/ar.js') }}?v=1"></script>
</body>
</html>
