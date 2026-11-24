@extends('layouts.app', ['title' => 'Points and paths · '.$map->name])

@section('content')
@php
    $facilityTypes = ['toilet' => 'Toilet', 'prayer_room' => 'Prayer room', 'information' => 'Information desk', 'exit' => 'Exit', 'atm' => 'ATM', 'lift' => 'Lift', 'baby_care' => 'Baby care', 'first_aid' => 'First aid', 'other' => 'Other'];
@endphp
<style>
    .hashkey { font:600 17px/1.2 ui-monospace,SFMono-Regular,Consolas,monospace; letter-spacing:.04em; color:#fff; background:var(--bg); border:1px dashed var(--accent); border-radius:8px; padding:5px 9px; display:inline-block; user-select:all; }
    textarea { background:var(--bg); color:var(--text); border:1px solid var(--line); border-radius:6px; padding:8px 10px; font:13px ui-monospace,Consolas,monospace; width:100%; }
    .badge.warn { color:var(--warn); border-color:#5a4a2a; }
    details.edit { margin-top:8px; }
    details.edit summary { cursor:pointer; color:var(--accent); }
    details.edit form { margin-top:8px; }
    [data-kind-field], [data-graph-field] { display:none; }
</style>
<h1>Points and paths: {{ $map->name }}</h1>
<p class="lead">Add what the scan and the Blender export missed. A <b>point</b> is a node, a shop entrance, a facility or a QR code; it is joined to the nearest walkway of its floor (at most {{ (int) config('mapservice.end_point_link_max_m', 6) }} m away) and gets a unique <b>key</b>, so apps can route to it with <code>point:&lt;key&gt;</code>. Points have a height above the floor for their marker: {{ \App\Models\MapEndPoint::DEFAULT_HEIGHT_M }} m unless you change it ({{ \App\Models\MapEndPoint::DEFAULT_QR_HEIGHT_M }} m for QR codes). A <b>path</b> is a walkway you draw corner by corner; a corner within 0.5 m of a node, a path end or a walkway joins it, and two paths that meet at an end can be merged. Everything can be edited, and is kept when a new version of this mall's map is imported. The same tools are in the <a href="{{ route('admin.maps.show', $map) }}">3D editor</a>. <a href="{{ route('admin.maps.index') }}">Back to maps</a></p>



<div class="card">
    <h2>Add a point</h2>
    @include('admin.maps.partials.point-form', ['point' => null])
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
    <h2>Navigation graph</h2>
    <p class="muted">Change the graph from the map package itself: move or remove a node, connect two nodes, make a walkway one way or not step-free, or remove it. In the 3D editor use <b>✎ Graph</b> and click a node or a walkway. Each change is kept, replayed after rebuilds and in new map versions, and can be edited (other nodes, another place, other settings) or undone.</p>
    <form method="post" action="{{ route('admin.maps.graph.store', $map) }}" class="grid" id="graph-form">
        @csrf
        <label>Change
            <select name="action" id="graph-action">
                <option value="move_node">Move a node</option>
                <option value="remove_node">Remove a node</option>
                <option value="connect">Connect two nodes</option>
                <option value="edit_edge">Change a walkway</option>
                <option value="remove_edge">Remove a walkway</option>
            </select>
        </label>
        <label data-graph-field="move_node remove_node">Node <input type="text" name="node_code" list="node-codes" maxlength="80"></label>
        <label data-graph-field="connect edit_edge remove_edge">From node <input type="text" name="from_code" list="node-codes" maxlength="80"></label>
        <label data-graph-field="connect edit_edge remove_edge">To node <input type="text" name="to_code" list="node-codes" maxlength="80"></label>
        <label data-graph-field="move_node">New x (metres) <input type="number" name="x" step="0.01"></label>
        <label data-graph-field="move_node">New y (metres) <input type="number" name="y" step="0.01"></label>
        <label data-graph-field="edit_edge">Direction
            <select name="direction">
                <option value="">(unchanged)</option>
                <option value="both">Both ways</option>
                <option value="forward">One way, from → to</option>
                <option value="backward">One way, to → from</option>
            </select>
        </label>
        <label data-graph-field="connect edit_edge">Step-free
            <select name="step_free">
                <option value="">(unchanged)</option>
                <option value="1">Step-free</option>
                <option value="0">Not step-free (stairs)</option>
            </select>
        </label>
        <label class="check" data-graph-field="connect"><input type="hidden" name="one_way" value="0"><input type="checkbox" name="one_way" value="1"> One way (from → to)</label>
        <div><button class="primary" type="submit">Apply change</button></div>
    </form>
    <datalist id="node-codes">
        @foreach ($nodeCodes as $code)
            <option value="{{ $code }}"></option>
        @endforeach
    </datalist>
    @if ($graphEdits->isEmpty())
        <p class="muted" style="margin-bottom:0">No changes yet.</p>
    @else
        <table style="margin-top:12px">
            <thead><tr><th>Key</th><th>Change</th><th></th></tr></thead>
            <tbody>
            @foreach ($graphEdits as $edit)
                <tr>
                    <td><span class="hashkey">{{ $edit->key }}</span></td>
                    <td>
                        {{ $edit->describe() }}@if ($edit->problem)<br><span class="badge bad">not applied</span> <span class="muted">{{ $edit->problem }}</span>@endif
                        @php($data = $edit->data ?? [])
                        <details class="edit">
                            <summary>Edit</summary>
                            <form method="post" action="{{ route('admin.maps.graph.update', [$map, $edit]) }}" class="grid">
                                @csrf @method('patch')
                                @if (in_array($edit->action, ['move_node', 'remove_node'], true))
                                    <label>Node <input type="text" name="node_code" value="{{ $edit->node_code }}" list="node-codes" maxlength="80" required></label>
                                @else
                                    <label>From node <input type="text" name="from_code" value="{{ $edit->from_code }}" list="node-codes" maxlength="80" required></label>
                                    <label>To node <input type="text" name="to_code" value="{{ $edit->to_code }}" list="node-codes" maxlength="80" required></label>
                                @endif
                                @if ($edit->action === 'move_node')
                                    <label>New x (metres) <input type="number" name="x" step="0.01" value="{{ $data['x'] ?? '' }}" required></label>
                                    <label>New y (metres) <input type="number" name="y" step="0.01" value="{{ $data['y'] ?? '' }}" required></label>
                                @elseif ($edit->action === 'connect')
                                    <div>
                                        <label class="check"><input type="hidden" name="step_free" value="0"><input type="checkbox" name="step_free" value="1" @checked($data['step_free'] ?? true)> Step-free</label>
                                        <label class="check"><input type="hidden" name="one_way" value="0"><input type="checkbox" name="one_way" value="1" @checked($data['one_way'] ?? false)> One way (from → to)</label>
                                    </div>
                                @elseif ($edit->action === 'edit_edge')
                                    <label>Direction
                                        <select name="direction">
                                            <option value="" @selected(! isset($data['direction']))>(unchanged)</option>
                                            <option value="both" @selected(($data['direction'] ?? null) === 'both')>Both ways</option>
                                            <option value="forward" @selected(($data['direction'] ?? null) === 'forward')>One way, from → to</option>
                                            <option value="backward" @selected(($data['direction'] ?? null) === 'backward')>One way, to → from</option>
                                        </select>
                                    </label>
                                    <label>Step-free
                                        <select name="step_free">
                                            <option value="" @selected(! isset($data['step_free']))>(unchanged)</option>
                                            <option value="1" @selected(($data['step_free'] ?? null) === true)>Step-free</option>
                                            <option value="0" @selected(($data['step_free'] ?? null) === false)>Not step-free (stairs)</option>
                                        </select>
                                    </label>
                                @endif
                                <div><button class="primary" type="submit">Save change</button></div>
                            </form>
                        </details>
                    </td>
                    <td class="actions">
                        <form method="post" action="{{ route('admin.maps.graph.destroy', [$map, $edit]) }}" class="inline">
                            @csrf @method('delete')
                            <button type="submit">Undo</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
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
                        <div><span class="badge">{{ $point['kind_label'] }}</span> <b>{{ $point['name'] }}</b>
                            @if ($point['kind'] === 'shop_entrance') · unit <code>{{ $point['unit_code'] }}</code>{{ $point['is_primary'] ? ' · main door' : '' }} @endif
                            @if ($point['kind'] === 'facility') · <code>{{ $point['facility_type'] }}</code> @endif
                            @if ($point['kind'] === 'checkpoint') · <code>{{ $point['checkpoint_code'] }}</code>, facing {{ (int) round($point['qr']['yaw_deg'] ?? 0) }}° @endif
                        </div>
                        <div class="muted" style="font-size:12px;margin-top:4px">
                            <code>{{ $point['place'] }}</code>
                            @if ($point['app_url']) · <a href="{{ $point['app_url'] }}" target="_blank" rel="noopener">open in the Smart Mall app</a>@endif
                            @if ($point['qr_url']) · QR address <code>{{ $point['qr_url'] }}</code> (print it on <a href="{{ route('admin.checkpoints.index') }}">QR codes</a>)@endif
                        </div>
                        <details class="edit">
                            <summary>Edit</summary>
                            @include('admin.maps.partials.point-form', ['point' => $point])
                        </details>
                    </td>
                    <td class="muted">
                        floor {{ $point['floor_code'] }} · x {{ number_format($point['position']['x'], 2) }} · y {{ number_format($point['position']['y'], 2) }} · {{ number_format((float) $point['height_m'], 2) }} m high<br>
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
            <thead><tr><th>Key</th><th>Name and corners</th><th>Where</th><th></th></tr></thead>
            <tbody>
            @foreach ($paths as $path)
                <tr>
                    <td><span class="hashkey">{{ $path['key'] }}</span></td>
                    <td>
                        <b>{{ $path['name'] ?? 'Path' }}</b>
                        <details class="edit">
                            <summary>Edit</summary>
                            <form method="post" action="{{ $path['update_url'] }}" class="grid">
                                @csrf @method('patch')
                                <label>Name <input type="text" name="name" value="{{ $path['name'] }}" maxlength="120" placeholder="(no name)"></label>
                                <label>Corners, one per line as x, y <textarea name="corners" rows="4">{{ collect($path['points'])->map(fn ($p) => $p[0].', '.$p[1])->implode("\n") }}</textarea></label>
                                <div>
                                    <label class="check"><input type="hidden" name="step_free" value="0"><input type="checkbox" name="step_free" value="1" @checked($path['step_free'])> Step-free</label>
                                    <label class="check"><input type="hidden" name="one_way" value="0"><input type="checkbox" name="one_way" value="1" @checked($path['one_way'])> One way</label>
                                </div>
                                <div><button class="primary" type="submit">Save path</button></div>
                            </form>
                            @php($partners = $paths->filter(fn ($other) => $other['key'] !== $path['key'] && $other['floor_code'] === $path['floor_code']))
                            @if ($partners->isNotEmpty())
                                <form method="post" action="{{ $path['merge_url'] }}" class="grid" style="margin-top:8px">
                                    @csrf
                                    <label>Merge with the path that meets this one at an end
                                        <select name="with">
                                            @foreach ($partners as $other)
                                                <option value="{{ $other['key'] }}">{{ $other['key'] }}{{ $other['name'] ? ' · '.$other['name'] : '' }} ({{ $other['length_m'] }} m)</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <div><button type="submit">Merge into {{ $path['key'] }}</button></div>
                                </form>
                            @endif
                        </details>
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
        document.querySelectorAll('form[data-point-form]').forEach(function (form) {
            var kind = form.querySelector('[name=kind]');
            var height = form.querySelector('[name=height]');
            function show() {
                form.querySelectorAll('[data-kind-field]').forEach(function (element) {
                    element.style.display = element.dataset.kindField.split(' ').indexOf(kind.value) >= 0 ? '' : 'none';
                });
                if (form.dataset.new === '1' && height && !height.dataset.touched) {
                    height.value = kind.value === 'checkpoint' ? '{{ \App\Models\MapEndPoint::DEFAULT_QR_HEIGHT_M }}' : '{{ \App\Models\MapEndPoint::DEFAULT_HEIGHT_M }}';
                }
            }
            if (height) {
                height.addEventListener('input', function () { height.dataset.touched = '1'; });
            }
            kind.addEventListener('change', show);
            show();
        });
        var graphAction = document.getElementById('graph-action');
        function showGraphFields() {
            document.querySelectorAll('[data-graph-field]').forEach(function (element) {
                element.style.display = element.dataset.graphField.split(' ').indexOf(graphAction.value) >= 0 ? '' : 'none';
            });
        }
        graphAction.addEventListener('change', showGraphFields);
        showGraphFields();
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                navigator.clipboard.writeText(button.dataset.copy).then(function () { button.textContent = 'Copied'; });
            });
        });
    })();
</script>
@endsection
