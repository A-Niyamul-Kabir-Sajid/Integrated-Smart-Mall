@extends('layouts.app', ['title' => 'Closures · '.$map->name])

@section('content')
<h1>Closures: {{ $map->name }}</h1>
<p class="lead">Close a walkway, lift or escalator for a while (cleaning, repairs, a broken lift). Routes asked for afterwards go around it; the embedded map shows the new route the next time it asks. The map's geometry does not change. <a href="{{ route('admin.maps.index') }}">Back to maps</a></p>

<div class="card">
    <h2>Close or reopen</h2>
    <form method="post" action="{{ route('admin.maps.closures.update', $map) }}" class="grid">
        @csrf
        <label>From node <input type="text" name="from" list="node-codes" value="{{ old('from') }}" required maxlength="80"></label>
        <label>To node <input type="text" name="to" list="node-codes" value="{{ old('to') }}" required maxlength="80"></label>
        <label>Reason (shown to administrators only) <input type="text" name="reason" value="{{ old('reason') }}" maxlength="200" placeholder="e.g. lift maintenance until 4 pm"></label>
        <label class="check"><input type="hidden" name="both_ways" value="0"><input type="checkbox" name="both_ways" value="1" checked> Both directions</label>
        <div style="display:flex; gap:6px">
            <button class="danger" type="submit" name="status" value="closed">Close</button>
            <button type="submit" name="status" value="open">Reopen</button>
        </div>
    </form>
    <datalist id="node-codes">
        @foreach ($nodeCodes as $code)
            <option value="{{ $code }}"></option>
        @endforeach
    </datalist>
    <p class="muted" style="margin-bottom:0">Node codes are those of the map package, for example <code>G-X-Central-Lift</code> and <code>1-X-Central-Lift</code> for a lift between two floors.</p>
</div>

<div class="card">
    <h2>Closed now</h2>
    @if ($closed->isEmpty())
        <p class="muted">Nothing is closed.</p>
    @else
        <table>
            <thead><tr><th>From</th><th>To</th><th>Reason</th><th></th></tr></thead>
            <tbody>
            @foreach ($closed as $edge)
                <tr>
                    <td><code>{{ $edge->fromNode->node_code }}</code></td>
                    <td><code>{{ $edge->toNode->node_code }}</code>@if ($edge->connector) <span class="muted">({{ $edge->connector->name }})</span>@endif</td>
                    <td class="muted">{{ $edge->closed_reason ?? '—' }}<br><span style="font-size:12px">since {{ $edge->status_changed_at?->format('Y-m-d H:i') }}</span></td>
                    <td class="actions">
                        <form method="post" action="{{ route('admin.maps.closures.update', $map) }}" class="inline">
                            @csrf
                            <input type="hidden" name="from" value="{{ $edge->fromNode->node_code }}">
                            <input type="hidden" name="to" value="{{ $edge->toNode->node_code }}">
                            <input type="hidden" name="both_ways" value="0">
                            <button type="submit" name="status" value="open">Reopen</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
