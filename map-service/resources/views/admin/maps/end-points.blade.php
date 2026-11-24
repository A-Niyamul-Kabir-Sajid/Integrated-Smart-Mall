@extends('layouts.app', ['title' => 'Points and paths · '.$map->name])

@section('content')
<style>
    .hashkey { font:600 17px/1.2 ui-monospace,SFMono-Regular,Consolas,monospace; letter-spacing:.04em; color:#fff; background:var(--bg); border:1px dashed var(--accent); border-radius:8px; padding:5px 9px; display:inline-block; user-select:all; }
    .rename { display:flex; gap:6px; max-width:300px; }
    .rename input { padding:4px 8px; }
    textarea { background:var(--bg); color:var(--text); border:1px solid var(--line); border-radius:6px; padding:8px 10px; font:13px ui-monospace,Consolas,monospace; width:100%; }
    .kind-only { display:none; }
    .badge.warn { color:var(--warn); border-color:#5a4a2a; }
</style>
<h1>Points and paths: {{ $map->name }}</h1>
<p class="lead">Add what the scan and the Blender export missed. A <b>point</b> is a node, a shop entrance, a facility or a QR code; it is joined to the nearest walkway of its floor (at most {{ (int) config('mapservice.end_point_link_max_m', 6) }} m away) and gets a unique <b>key</b>, so apps can route to it with <code>point:&lt;key&gt;</code>. A <b>path</b> is a walkway you draw corner by corner; a corner within 0.5 m of a node or walkway joins it. Both also work in the <a href="{{ route('admin.maps.show', $map) }}">3D editor</a> (<b>＋ Point</b>, <b>＋ QR code</b>, <b>＋ Path</b>), and both are kept when a new version of this mall's map is imported. <a href="{{ route('admin.maps.index') }}">Back to maps</a></p>

<div class="card">
    <h2>Add a point</h2>
    <form method="post" action="{{ route('admin.maps.end-points.store', $map) }}" class="grid" id="point-form">
        @csrf
        <label>What is it
            <select name="kind" id="point-kind" required>
                @foreach ($kinds as $value => $label)
                    <option value="{{ $value }}" @selected(old('kind', 'node') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Name <input type="text" name="name" value="{{ old('name') }}" maxlength="120" required placeholder="e.g. Kiosk by the south entrance"></label>
        <label>Floor
            <select name="floor_code" required>
                @foreach ($floors as $floor)
                    <option value="{{ $floor->floor_code }}" @selected(old('floor_code') === $floor->floor_code)>{{ $floor->floor_code }} · {{ $floor->name }}</option>
                @endforeach
            </select>
        </label>
        <label>x (metres) <input type="number" name="x" step="0.01" value="{{ old('x') }}" required></label>
        <label>y (metres) <input type="number" name="y" step="0.01" value="{{ old('y') }}" required></label>
        <label class="kind-only" data-kind="shop_entrance">Shop unit code <input type="text" name="unit_code" value="{{ old('unit_code') }}" maxlength="40" placeholder="G-05"></label>
        <label class="check kind-only" data-kind="shop_entrance"><input type="hidden" name="is_primary" value="0"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))> Main door</label>
        <label class="kind-only" data-kind="facility">Facility type
            <select name="facility_type">
                @foreach (['toilet' => 'Toilet', 'prayer_room' => 'Prayer room', 'information' => 'Information desk', 'exit' => 'Exit', 'atm' => 'ATM', 'lift' => 'Lift', 'baby_care' => 'Baby care', 'first_aid' => 'First aid', 'other' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('facility_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="kind-only" data-kind="checkpoint">QR code <input type="text" name="checkpoint_code" value="{{ old('checkpoint_code') }}" maxlength="60" placeholder="G-C10"></label>
        <label class="kind-only" data-kind="checkpoint">Height above the floor (m) <input type="number" name="qr_height" step="0.01" min="0.2" max="3" value="{{ old('qr_height', '1.4') }}"></label>
        <label class="kind-only" data-kind="checkpoint">Facing (degrees: 0 east, 90 north, 180 west, 270 south) <input type="number" name="qr_yaw" step="1" min="0" max="359" value="{{ old('qr_yaw', '0') }}"></label>
        <div><button class="primary" type="submit">Add point</button></div>
    </form>
    <p class="muted" style="margin-bottom:0">x and y are metres in the map's frame; the 3D editor shows them under the mouse pointer. For a QR code, x and y are where it hangs on the wall; visitors stand 0.8 m in front of it.</p>
</div>

<div class="card">
    <h2>Add a path</h2>
    <form method="post" action="{{ route('admin.maps.paths.store', $map) }}" class="grid">
        @csrf
        <label>Floor
            <select name="floor_code" required>
                @foreach ($floors as $floor)
                    <option value="{{ $floor->floor_code }}">{{ $floor->floor_code }} · {{ $floor->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Corners, one per line as x, y <textarea name="corners" rows="4" placeholder="2.0, 1.0&#10;2.0, 8.5&#10;5.5, 8.5" required>{{ old('corners') }}</textarea></label>
        <label>Name (optional) <input type="text" name="name" value="{{ old('name') }}" maxlength="120" placeholder="e.g. Shortcut through the atrium"></label>
        <div>
            <label class="check"><input type="hidden" name="step_free" value="0"><input type="checkbox" name="step_free" value="1" checked> Step-free (no stairs)</label>
            <label class="check"><input type="hidden" name="one_way" value="0"><input type="checkbox" name="one_way" value="1"> One way (first corner to last)</label>
        </div>
        <div><button class="primary" type="submit">Add path</button></div>
    </form>
</div>

<div class="card">
    <h2>Points</h2>
    @if ($points->isEmpty())
        <p class="muted">None yet.</p>
    @else
        <table>
            <thead><tr><th>Key</th><th>What and name</th><th>Where</th><th></th></tr></thead>
            <tbody>
            @foreach ($points as $point)
                <tr>
                    <td>
                        <span class="hashkey" title="Key of {{ $point['name'] }}">{{ $point['key'] }}</span>
                        <button type="button" data-copy="{{ $point['key'] }}">Copy</button>
                    </td>
                    <td>
                        <div><span class="badge">{{ $point['kind_label'] }}</span>
                            @if ($point['kind'] === 'shop_entrance') unit <code>{{ $point['unit_code'] }}</code>{{ $point['is_primary'] ? ' · main door' : '' }} @endif
                            @if ($point['kind'] === 'facility') <code>{{ $point['facility_type'] }}</code> @endif
                            @if ($point['kind'] === 'checkpoint') <code>{{ $point['checkpoint_code'] }}</code> @endif
                        </div>
                        <form method="post" action="{{ $point['update_url'] }}" class="rename" style="margin-top:6px">
                            @csrf @method('patch')
                            <input type="text" name="name" value="{{ $point['name'] }}" maxlength="120" aria-label="Name of {{ $point['key'] }}">
                            <button type="submit">Rename</button>
                        </form>
                        <div class="muted" style="font-size:12px;margin-top:4px">
                            <code>{{ $point['place'] }}</code>
                            @if ($point['app_url']) · <a href="{{ $point['app_url'] }}" target="_blank" rel="noopener">open in the Smart Mall app</a>@endif
                            @if ($point['qr_url']) · QR address <code>{{ $point['qr_url'] }}</code> (print it on <a href="{{ route('admin.checkpoints.index') }}">QR codes</a>)@endif
                        </div>
                    </td>
                    <td class="muted">
                        floor {{ $point['floor_code'] }} · x {{ number_format($point['position']['x'], 2) }} · y {{ number_format($point['position']['y'], 2) }}<br>
                        @if ($point['problem'])
                            <span class="badge bad">not applied</span> {{ $point['problem'] }}
                        @elseif ($point['link_m'] !== null)
                            <span class="badge ok">on the map</span> {{ number_format((float) $point['link_m'], 1) }} m from the walkway
                        @elseif ($point['connected'])
                            <span class="badge ok">on the map</span> joined by a path
                        @else
                            <span class="badge warn">not connected</span> no walkway within {{ (int) config('mapservice.end_point_link_max_m', 6) }} m: draw a path to it
                        @endif
                    </td>
                    <td class="actions">
                        <form method="post" action="{{ $point['update_url'] }}" class="inline" onsubmit="return confirm('Delete {{ addslashes($point['name']) }} ({{ $point['key'] }})? Links that use its key stop working.')">
                            @csrf @method('delete')
                            <button class="danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="card">
    <h2>Paths</h2>
    @if ($paths->isEmpty())
        <p class="muted">None yet.</p>
    @else
        <table>
            <thead><tr><th>Key</th><th>Name</th><th>Where</th><th></th></tr></thead>
            <tbody>
            @foreach ($paths as $path)
                <tr>
                    <td><span class="hashkey">{{ $path['key'] }}</span></td>
                    <td>
                        <form method="post" action="{{ $path['update_url'] }}" class="rename">
                            @csrf @method('patch')
                            <input type="text" name="name" value="{{ $path['name'] }}" maxlength="120" placeholder="(no name)" aria-label="Name of path {{ $path['key'] }}">
                            <button type="submit">Rename</button>
                        </form>
                    </td>
                    <td class="muted">
                        floor {{ $path['floor_code'] }} · {{ $path['corners'] }} corners · {{ $path['length_m'] }} m · {{ $path['step_free'] ? 'step-free' : 'not step-free' }}{{ $path['one_way'] ? ' · one way' : '' }}<br>
                        @if ($path['problem'])
                            <span class="badge bad">not applied</span> {{ $path['problem'] }}
                        @elseif ($path['joined'])
                            <span class="badge ok">joined to the map</span>
                        @else
                            <span class="badge warn">not joined</span> no corner is near the rest of the graph
                        @endif
                    </td>
                    <td class="actions">
                        <form method="post" action="{{ $path['update_url'] }}" class="inline" onsubmit="return confirm('Delete path {{ $path['key'] }}?')">
                            @csrf @method('delete')
                            <button class="danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
<script>
    (function () {
        var kind = document.getElementById('point-kind');
        function show() {
            document.querySelectorAll('.kind-only').forEach(function (element) {
                element.style.display = element.dataset.kind === kind.value ? '' : 'none';
            });
        }
        kind.addEventListener('change', show);
        show();
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                navigator.clipboard.writeText(button.dataset.copy).then(function () { button.textContent = 'Copied'; });
            });
        });
    })();
</script>
@endsection
