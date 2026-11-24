@extends('layouts.app', ['title' => 'End points · '.$map->name])

@section('content')
<style>
    .hashkey { font:600 18px/1.2 ui-monospace,SFMono-Regular,Consolas,monospace; letter-spacing:.04em; color:#fff; background:var(--bg); border:1px dashed var(--accent); border-radius:8px; padding:6px 10px; display:inline-block; user-select:all; }
    .copy { margin-left:6px; }
    .rename { display:flex; gap:6px; max-width:280px; }
    .rename input { padding:4px 8px; }
</style>
<h1>End points: {{ $map->name }}</h1>
<p class="lead">An end point is a destination you add to the map: a kiosk, a meeting place, a room without a shop. It is joined to the nearest walkway of its floor (at most {{ (int) config('mapservice.end_point_link_max_m', 6) }} m away) and gets a <b>key</b>. Apps route to it with <code>point:&lt;key&gt;</code>; the key stays the same when a new version of this mall's map is imported. Add one below, or in the <a href="{{ route('admin.maps.show', $map) }}">3D editor</a> with <b>＋ End point</b> and a click on the floor. <a href="{{ route('admin.maps.index') }}">Back to maps</a></p>

<div class="card">
    <h2>Add an end point</h2>
    <form method="post" action="{{ route('admin.maps.end-points.store', $map) }}" class="grid">
        @csrf
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
        <div><button class="primary" type="submit">Add end point</button></div>
    </form>
    <p class="muted" style="margin-bottom:0">x and y are metres in the map's frame; the 3D editor shows them under the mouse pointer.</p>
</div>

<div class="card">
    <h2>End points of this map</h2>
    @if ($points->isEmpty())
        <p class="muted">None yet.</p>
    @else
        <table>
            <thead><tr><th>Key</th><th>Name and place</th><th>Where</th><th></th></tr></thead>
            <tbody>
            @foreach ($points as $point)
                <tr>
                    <td>
                        <span class="hashkey" title="Key of {{ $point->name }}">{{ $point->key }}</span>
                        <button type="button" class="copy" data-copy="{{ $point->key }}">Copy</button>
                    </td>
                    <td>
                        <form method="post" action="{{ route('admin.maps.end-points.update', [$map, $point]) }}" class="rename">
                            @csrf @method('patch')
                            <input type="text" name="name" value="{{ $point->name }}" maxlength="120" aria-label="Name of {{ $point->key }}">
                            <button type="submit">Rename</button>
                        </form>
                        <div class="muted" style="font-size:12px;margin-top:4px">
                            <code>{{ $point->place() }}</code>
                            @if ($url = $appUrl($point))
                                · <a href="{{ $url }}" target="_blank" rel="noopener">open in the Smart Mall app</a>
                            @endif
                        </div>
                    </td>
                    <td class="muted">
                        floor {{ $point->floor_code }} · x {{ number_format($point->x_m, 2) }} · y {{ number_format($point->y_m, 2) }}<br>
                        @if ($point->isLinked())
                            <span class="badge ok">on the map</span> {{ number_format((float) $point->link_m, 1) }} m from the walkway
                        @else
                            <span class="badge bad">not on the map</span> no walkway near it
                        @endif
                    </td>
                    <td class="actions">
                        <form method="post" action="{{ route('admin.maps.end-points.destroy', [$map, $point]) }}" class="inline" onsubmit="return confirm('Delete {{ addslashes($point->name) }} ({{ $point->key }})? Links that use its key stop working.')">
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
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            navigator.clipboard.writeText(button.dataset.copy).then(function () { button.textContent = 'Copied'; });
        });
    });
</script>
@endsection
