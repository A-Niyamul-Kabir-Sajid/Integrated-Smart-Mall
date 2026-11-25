@extends('layouts.app', ['title' => 'API documentation'])

@section('content')
<style>
    .docs h2 { font-size:18px; margin:28px 0 10px; padding-top:8px; border-top:1px solid var(--line); }
    .docs h3 { font-size:15px; margin:18px 0 8px; }
    .docs pre { background:var(--bg); border:1px solid var(--line); border-radius:8px; padding:12px; overflow-x:auto; font:13px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace; white-space:pre; }
    .docs pre code { background:none; padding:0; }
    .docs nav.toc { display:flex; flex-wrap:wrap; gap:6px 14px; }
    .docs nav.toc a, .docs a { color:var(--accent); }
    .docs td code { white-space:nowrap; }
    .docs ul { padding-left:20px; }
    .docs li { margin:4px 0; }
    .method { display:inline-block; min-width:52px; font-weight:700; font-size:12px; }
</style>
<div class="docs">
<h1>ISM Map Service API</h1>
<p class="lead">Indoor maps of the malls, routes between shops and facilities, QR start points, an embeddable map window and AR directions on Android phones. The Smart Mall app is the first client. Base address: <code>{{ $base }}</code></p>

<nav class="toc card" aria-label="Contents">
    <a href="#start">Getting started</a>
    <a href="#auth">Keys and abilities</a>
    <a href="#endpoints">Endpoints</a>
    <a href="#places">Places</a>
    <a href="#routes">Routes</a>
    <a href="#checkpoints">QR checkpoints</a>
    <a href="#embed">Embedded map</a>
    <a href="#messages">Embed messages</a>
    <a href="#ar">AR directions</a>
    <a href="#closures">Closures</a>
    <a href="#errors">Errors and limits</a>
    <a href="#frame">Coordinates</a>
</nav>

<h2 id="start">Getting started</h2>
<ol>
    <li>An administrator of this service imports a map package from the Blender pipeline, sets its <b>mall</b> to the Smart Mall app's mall slug (for example <code>kuet-city-mall</code>) and publishes it. A mall has at most one published map; publishing a new one replaces the old one at once.</li>
    <li>The administrator creates an <b>API key</b> for your app on the API keys page, with the abilities it needs and the web origins allowed to show the embedded map (for example <code>http://127.0.0.1:8000</code>). The key is shown once.</li>
    <li>Your server keeps the key (never put it in a web page) and calls this API. To show the map to a visitor, your server asks for an <a href="#embed">embed link</a> and puts it in an <code>&lt;iframe&gt;</code>.</li>
</ol>
<pre><code>curl -H "Authorization: Bearer ism_..." {{ $base }}/malls/kuet-city-mall</code></pre>

<h2 id="auth">Keys and abilities</h2>
<p>Send the key as <code>Authorization: Bearer ism_...</code> or <code>X-Api-Key: ism_...</code>. The key is checked before anything else, so a wrong key learns nothing about which maps exist. Only published maps are visible.</p>
<table>
    <thead><tr><th>Ability</th><th>Allows</th></tr></thead>
    <tbody>
    @foreach ($abilities as $ability => $description)
        <tr><td><code>{{ $ability }}</code></td><td>{{ $description }}@if ($ability === 'maps:read'), routes, QR scans and embed links @endif</td></tr>
    @endforeach
    </tbody>
</table>

<h2 id="endpoints">Endpoints</h2>
<table>
    <thead><tr><th>Request</th><th>Ability</th><th>Answer</th></tr></thead>
    <tbody>
        <tr><td><span class="method">GET</span><code>/malls/{mall}</code></td><td><code>maps:read</code></td><td>The mall's published map (<code>id</code>, <code>name</code>, <code>floor_codes</code>, <code>stats</code>) and its <code>places</code>: floors, units, facilities, facility types, end points, QR codes</td></tr>
        <tr><td><span class="method">GET POST</span><code>/malls/{mall}/routes</code></td><td><code>maps:read</code></td><td>A <a href="#routes">route</a> on the mall's published map</td></tr>
        <tr><td><span class="method">GET</span><code>/checkpoints/{slug}</code></td><td><code>maps:read</code></td><td>Where a scanned <a href="#checkpoints">QR code</a> puts the visitor</td></tr>
        <tr><td><span class="method">POST</span><code>/embed-links</code></td><td><code>maps:read</code></td><td>A signed link for the <a href="#embed">embedded map</a> (201)</td></tr>
        <tr><td><span class="method">GET</span><code>/maps</code></td><td><code>maps:read</code></td><td>Every published map</td></tr>
        <tr><td><span class="method">GET</span><code>/maps/{id}</code></td><td><code>maps:read</code></td><td>The whole map package with signed file links (<code>file_urls</code>, valid {{ $fileMinutes }} minutes)</td></tr>
        <tr><td><span class="method">GET</span><code>/maps/{id}/places</code></td><td><code>maps:read</code></td><td>The <a href="#places">places</a> of one map</td></tr>
        <tr><td><span class="method">GET POST</span><code>/maps/{id}/routes</code></td><td><code>maps:read</code></td><td>A route on one map</td></tr>
        <tr><td><span class="method">GET</span><code>/maps/{id}/closures</code></td><td><code>maps:read</code></td><td>Walkways closed now</td></tr>
        <tr><td><span class="method">PATCH</span><code>/maps/{id}/edges</code></td><td><code>graph:write</code></td><td>Close or reopen a walkway (<a href="#closures">closures</a>)</td></tr>
        <tr><td><span class="method">GET</span><code>/maps/{id}/annotations</code></td><td><code>maps:read</code></td><td>Shop signs and advertisements placed in 3D; filters <code>floor</code>, <code>unit_code</code>, <code>kind</code></td></tr>
        <tr><td><span class="method">POST PUT DELETE</span><code>/maps/{id}/annotations[/{code}]</code></td><td><code>annotations:write</code></td><td>Create, change or delete a sign or advertisement</td></tr>
    </tbody>
</table>

<h2 id="places">Places</h2>
<p>Starts and destinations are written <code>kind:value</code>. The places endpoints list them with this form in <code>place</code>.</p>
<table>
    <thead><tr><th>Place</th><th>Means</th></tr></thead>
    <tbody>
        <tr><td><code>checkpoint:&lt;slug&gt;</code></td><td>Where the visitor stands at a scanned QR code (the slug printed in the code)</td></tr>
        <tr><td><code>unit:G-101</code></td><td>A shop by its unit code in the Smart Mall app; the nearest of its doors is used</td></tr>
        <tr><td><code>facility:Toilets G</code></td><td>One facility by name</td></tr>
        <tr><td><code>facility-type:toilet</code></td><td>The nearest facility of that type</td></tr>
        <tr><td><code>point:k7m2x9q4ab</code></td><td>A point an administrator added (a node, shop entrance, facility or QR code); the unique key is shown on the map's Points and paths page and in the 3D editor, and stays the same for new map versions of the mall. Places list them under <code>end_points</code> with their <code>kind</code>.</td></tr>
        <tr><td><code>node:G-N0001</code></td><td>One point of the navigation graph</td></tr>
    </tbody>
</table>

<h2 id="routes">Routes</h2>
<p>Parameters (query string or JSON body):</p>
<ul>
    <li><code>from</code> (required): a place.</li>
    <li><code>to</code> (required): a place, or a list of up to 10 places visited in order.</li>
    <li><code>step_free</code>: <code>true</code> avoids stairs and escalators (lifts and ramps only).</li>
    <li><code>cost</code>: <code>time</code> (default, fastest) or <code>distance</code> (shortest).</li>
</ul>
<p>Routes use only open walkways and follow the direction of escalators. Nothing is stored: after a new QR scan or a closure, ask again.</p>
<pre><code>POST {{ $base }}/malls/kuet-city-mall/routes
{"from": "checkpoint:k3j9x2m1q8wa", "to": "unit:1-205", "step_free": true}

200
{"data": {
  "map_id": "1ea35cd9-...", "mall_ref": "kuet-city-mall", "cost": "time", "step_free": true,
  "from": {"place": "checkpoint:k3j9x2m1q8wa", "label": "Main entrance", "node_code": "G-C01"},
  "to": [{"place": "unit:1-205", "label": "Shop 1-205", "node_code": "E-1-205"}],
  "total": {"length_m": 37.0, "seconds": 61.7, "minutes": 2},
  "floors": ["G", "1"],
  "path": [{"node_code": "G-C01", "floor_code": "G", "node_type": "checkpoint", "position": {"x": 0, "y": 0, "z": 0}}, ...],
  "legs": [{"to": "unit:1-205", "label": "Shop 1-205", "length_m": 37.0, "seconds": 61.7, "from_index": 0, "to_index": 6}],
  "steps": [
    {"type": "start", "instruction": "Start at Main entrance.", "floor_code": "G", ...},
    {"type": "walk", "instruction": "Walk 10 m.", "length_m": 10.0, "seconds": 8.3, ...},
    {"type": "connector", "instruction": "Take Central Lift up to First floor.", "to_floor_code": "1", ...},
    {"type": "walk", "instruction": "Turn right and walk 20 m.", ...},
    {"type": "arrive", "instruction": "Arrive at Shop 1-205.", ...}
  ]
}}</code></pre>
<p>Step types: <code>start</code>, <code>walk</code> (a new walk step begins at each turn of 30 degrees or more), <code>connector</code> (lift, escalator or stairs), <code>stop</code> (an intermediate destination) and <code>arrive</code>.</p>

<h2 id="checkpoints">QR checkpoints</h2>
<p>Each QR code printed in the mall holds only an address of the Smart Mall app with a random slug, for example <code>http://127.0.0.1:8000/malls/kuet-city-mall/map/qr/k3j9x2m1q8wa</code>. The slug stays the same when a new map version is published. Your app resolves it:</p>
<pre><code>GET {{ $base }}/checkpoints/k3j9x2m1q8wa

200 {"data": {"slug": "k3j9x2m1q8wa", "checkpoint_code": "G-C01", "label": "Main entrance", "mall_ref": "kuet-city-mall",
      "map_id": "...", "floor_code": "G", "node": {...}, "qr": {"x_m": -0.8, "y_m": 0, "z_m": 1.4, "yaw_deg": 0, ...},
      "start": "checkpoint:k3j9x2m1q8wa"}}</code></pre>
<p>Refusals carry a <code>reason</code>: <code>unknown</code> (404), <code>retired</code> (410: the code was taken down), <code>not_on_map</code> (409: the current map does not have it) and <code>no_map</code> (404). Use <code>start</code> as the <code>from</code> of a route or an embed link.</p>

<h2 id="embed">Embedded map</h2>
<p>The embedded map is a page of this service shown in an iframe of your site: floor plans, walkways, shops and facilities, the route with its steps, floor tabs, zoom and pan, English or Bangla labels. It works on phones and needs no key in the browser.</p>
<h3>1. Your server asks for a link</h3>
<pre><code>POST {{ $base }}/embed-links
Authorization: Bearer ism_...
{"mall_ref": "kuet-city-mall", "from": "checkpoint:k3j9x2m1q8wa", "to": "unit:1-205", "step_free": false, "lang": "bn"}

201 {"data": {"url": "{{ url('/embed/maps') }}/1ea35cd9-...?expires=...&amp;signature=...", "expires_at": "...", "map_id": "...", "mall_ref": "kuet-city-mall"}}</code></pre>
<ul>
    <li><code>mall_ref</code> or <code>map_id</code> (one is required); <code>from</code> and <code>to</code> (optional places, <code>to</code> may be a list); <code>step_free</code>; <code>cost</code>; <code>lang</code> <code>en</code> or <code>bn</code>; <code>minutes</code> 1 to 60 (default {{ $linkMinutes }}).</li>
    <li>The link must be opened within its minutes; the opened page then works for {{ $sessionMinutes }} minutes. Create a new link for each page view.</li>
    <li>The key needs at least one allowed origin; otherwise the answer is 422 with reason <code>no_origins</code>.</li>
</ul>
<h3>2. Your page shows it</h3>
<pre><code>&lt;iframe src="(the url)" title="Mall map" style="width:100%;height:600px;border:0" allow="fullscreen; xr-spatial-tracking"&gt;&lt;/iframe&gt;</code></pre>
<p>Only the key's allowed origins may frame the page (<code>Content-Security-Policy: frame-ancestors</code>). Revoking the key or unpublishing the map ends every open embed: its next request answers "This map has expired. Reload the page." A tampered or expired link answers 403.</p>

<h2 id="messages">Embed messages</h2>
<p>The embedded map tells your page what happens with <code>postMessage</code> (sent only to the key's allowed origins). Every message has <code>source: "ism-map"</code> and <code>mapId</code>.</p>
<table>
    <thead><tr><th>Type</th><th>Sent when</th><th>Fields</th></tr></thead>
    <tbody>
        <tr><td><code>ism-map:ready</code></td><td>The map has loaded</td><td><code>floors</code>, <code>name</code></td></tr>
        <tr><td><code>ism-map:route</code></td><td>A route is shown (also when it changes after a closure; the map asks again every minute)</td><td><code>total</code>, <code>floors</code>, <code>from</code>, <code>to</code>, <code>step_free</code></td></tr>
        <tr><td><code>ism-map:floor</code></td><td>Another floor is shown</td><td><code>floor</code></td></tr>
        <tr><td><code>ism-map:error</code></td><td>A route or the map could not be loaded</td><td><code>reason</code> (<code>unknown_place</code>, <code>unreachable</code>, <code>session</code>, ...), <code>message</code></td></tr>
    </tbody>
</table>
<p>Your page can steer the map (messages from other origins are ignored):</p>
<pre><code>const frame = document.querySelector('iframe').contentWindow;
frame.postMessage({type: 'ism-map:navigate', from: 'checkpoint:k3j9x2m1q8wa', to: 'facility-type:toilet', stepFree: true}, '{{ url('/') }}');
frame.postMessage({type: 'ism-map:floor', floor: '1'}, '{{ url('/') }}');

window.addEventListener('message', (event) => {
  if (event.origin !== '{{ url('/') }}' || event.data?.source !== 'ism-map') return;
  if (event.data.type === 'ism-map:route') console.log(event.data.total.minutes + ' min');
});</code></pre>

<h2 id="ar">AR directions</h2>
<p>On phones that can show AR (Chrome on Android with Google Play Services for AR), the embedded map shows <b>View in AR</b> once a route is shown. It opens this service's AR page in a new tab; your page needs nothing more than <code>xr-spatial-tracking</code> in the iframe's <code>allow</code> list, so the map can ask the browser whether AR works (without it, the button shows on every Android phone and the AR page checks). iPhones and computers keep the 2D map.</p>
<ul>
    <li>The visitor moves the phone over the floor until a ring appears, then lines it up with any QR code of the mall: the camera reads the code from about half a metre (Chrome's camera access and barcode reader), or the phone is held flat against the code and <b>Align</b> tapped. The route then starts at that code.</li>
    <li>Arrows on the floor, a pin at the destination, the next step with its distance, and the distance and time left. After a lift, escalator or stairs the visitor lines up again at a code of the new floor. More than 4 m off the route for a few seconds offers a new route from where the visitor stands.</li>
    <li>QR codes must be printed at their real size (18 cm unless the map package says otherwise): the phone measures its distance to a code by the code's size. Administrators print them from the QR codes page with <b>Print signs at real size</b>.</li>
    <li>WebXR needs a secure page: the service must be reached over https (or as <code>localhost</code> / <code>127.0.0.1</code>, for example through <code>adb reverse</code> while testing).</li>
</ul>
<p>The AR page cannot be framed (<code>frame-ancestors 'none'</code>); its link is signed, valid {{ $linkMinutes }} minutes, and asked for by the open embedded map with its own session, so your server does nothing extra.</p>

<h2 id="closures">Closures</h2>
<p>A walkway, lift or escalator can be closed for a while; routes avoid it at once. Administrators use the Closures page of a map; another system can use the API with <code>graph:write</code>:</p>
<pre><code>PATCH {{ $base }}/maps/{id}/edges
{"from": "G-X-Central-Lift", "to": "1-X-Central-Lift", "status": "closed", "reason": "Lift maintenance", "both_ways": true}</code></pre>
<p><code>status</code> is <code>closed</code> or <code>open</code>; <code>both_ways</code> defaults to true. Node codes come from the map package. 422 when the two nodes are not joined by a walkway.</p>

<h2 id="errors">Errors and limits</h2>
<table>
    <thead><tr><th>Status</th><th>Means</th></tr></thead>
    <tbody>
        <tr><td>401</td><td>No key, or a key that is unknown, revoked or expired</td></tr>
        <tr><td>403</td><td>The key lacks the ability; or a tampered or expired embed link</td></tr>
        <tr><td>404</td><td>Unknown or unpublished map, a mall without a published map, an unknown QR code</td></tr>
        <tr><td>409, 410</td><td>A QR code not on the current map, or retired</td></tr>
        <tr><td>422</td><td>Validation failed (<code>errors</code> per field), with <code>reason</code> <code>unknown_place</code>, <code>unreachable</code> or <code>no_origins</code> where it applies</td></tr>
        <tr><td>429</td><td>Too many requests: {{ $perMinute }} per minute per key</td></tr>
    </tbody>
</table>

<h2 id="frame">Coordinates</h2>
<p>All positions are in the map's frame <code>ism-mall-local-v1</code>: metres, x and y horizontal, z up, headings in degrees counterclockwise from +x. Floor models (glTF) are Y-up: <code>(x, z, -y)</code>. Plan images have pixel (0,0) at the top-left corner at <code>(world_origin_x_m, world_origin_y_m)</code>, <code>metres_per_pixel</code> per pixel, no rotation.</p>
</div>
@endsection
