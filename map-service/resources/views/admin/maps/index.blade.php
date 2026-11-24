@extends('layouts.app', ['title' => 'Maps'])

@section('content')
<style>.inline-mall { display:flex; gap:6px; margin-top:6px; max-width:260px; } .inline-mall input { padding:4px 8px; }</style>
<h1>Maps</h1>
<p class="lead">Map packages exported by the Blender pipeline. Open one in the 3D editor to place shop signs and advertisements. Only <b>published</b> maps are visible through the API.</p>

<div class="card">
    <h2>Import a map package</h2>
    @if ($packages === [])
        <p class="muted">No packages found under <code>{{ $importRoot }}</code>. Export one with <code>tools/blender/ism_map.py export --out tools/blender/work/&lt;name&gt;/package</code>, or use <code>php artisan map:import &lt;folder&gt;</code>.</p>
    @else
        <form method="post" action="{{ route('admin.maps.import') }}" class="grid">
            @csrf
            <label>Package folder (under the Blender work folder)
                <select name="folder" required>
                    @foreach ($packages as $package)
                        <option value="{{ $package }}" @selected(old('folder') === $package)>{{ $package }}</option>
                    @endforeach
                </select>
            </label>
            <label>Name <input type="text" name="name" value="{{ old('name', 'Sample floor') }}" maxlength="120" required></label>
            <label>Mall (the Smart Mall app's mall slug, optional) <input type="text" name="mall_ref" value="{{ old('mall_ref') }}" maxlength="100" placeholder="e.g. kuet-city-mall"></label>
            <label class="check"><input type="checkbox" name="publish" value="1" @checked(old('publish'))> Publish to the API now</label>
            <div><button class="primary" type="submit">Import</button></div>
        </form>
    @endif
</div>

<div class="card">
    <h2>Imported maps</h2>
    @if ($maps->isEmpty())
        <p class="muted">None yet.</p>
    @else
        <table>
            <thead><tr><th>Name</th><th>Status</th><th>Contents</th><th></th></tr></thead>
            <tbody>
            @foreach ($maps as $map)
                <tr>
                    <td>
                        <div>{{ $map->name }}</div>
                        <div class="muted" style="font-size:12px"><code>{{ $map->id }}</code></div>
                        <form method="post" action="{{ route('admin.maps.mall', $map) }}" class="inline-mall">
                            @csrf @method('patch')
                            <input type="text" name="mall_ref" value="{{ $map->mall_ref }}" maxlength="100" placeholder="mall slug" aria-label="Mall slug of {{ $map->name }}">
                            <button type="submit">Set mall</button>
                        </form>
                    </td>
                    <td><span class="badge {{ $map->isPublished() ? 'ok' : 'off' }}">{{ $map->isPublished() ? 'published' : 'draft' }}</span></td>
                    <td class="muted">
                        floors {{ implode(', ', $map->floor_codes) }} · {{ $map->nodes_count }} nodes, {{ $map->edges_count }} edges · {{ $map->stats['checkpoints'] ?? 0 }} QR codes<br>
                        {{ $map->annotations_count }} signs and advertisements · exported {{ $map->package_generated_at?->format('Y-m-d H:i') ?? 'unknown' }}
                    </td>
                    <td class="actions">
                        <a class="button primary" href="{{ route('admin.maps.show', $map) }}">Open 3D editor</a>
                        <a class="button" href="{{ route('admin.maps.closures', $map) }}">Closures</a>
                        <a class="button" href="{{ route('admin.maps.end-points', $map) }}">End points</a>
                        <form method="post" action="{{ route('admin.maps.status', $map) }}" class="inline">
                            @csrf @method('patch')
                            <input type="hidden" name="status" value="{{ $map->isPublished() ? 'draft' : 'published' }}">
                            <button type="submit">{{ $map->isPublished() ? 'Unpublish' : 'Publish' }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.maps.destroy', $map) }}" class="inline" onsubmit="return confirm('Delete {{ addslashes($map->name) }} and all its signs and advertisements?')">
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
@endsection
