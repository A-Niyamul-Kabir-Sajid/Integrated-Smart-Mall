<!doctype html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $map->name }}</title>
    <link rel="stylesheet" href="{{ asset('embed/embed.css') }}?v=3">
</head>
<body>
<div id="app" class="app" aria-busy="true">
    <form id="ask" class="ask" autocomplete="off">
        <label class="field"><span data-t="from">From</span>
            <select id="from" name="from"></select>
        </label>
        <label class="field grow"><span data-t="to">To</span>
            <select id="to" name="to"></select>
        </label>
        <label class="check"><input type="checkbox" id="step-free"> <span data-t="stepFree">Step-free</span></label>
        <button type="submit" id="go" class="go" data-t="go">Show route</button>
    </form>
    <div id="summary" class="summary" role="status" aria-live="polite"></div>
    <div class="body">
        <div class="mapbox">
            <div id="floors" class="floors" role="tablist" aria-label="Floors"></div>
            <svg id="map" class="map" role="img" aria-labelledby="map-title" tabindex="0"><title id="map-title">{{ $map->name }}</title></svg>
            <div class="zoom">
                <button type="button" id="zoom-in" aria-label="Zoom in">+</button>
                <button type="button" id="zoom-out" aria-label="Zoom out">−</button>
                <button type="button" id="zoom-fit" aria-label="Fit" data-t="fit">Fit</button>
            </div>
        </div>
        <ol id="steps" class="steps" aria-label="Steps"></ol>
    </div>
</div>
<script type="application/json" id="embed-config">@json($config)</script>
<script src="{{ asset('embed/embed.js') }}?v=4" defer></script>
</body>
</html>
