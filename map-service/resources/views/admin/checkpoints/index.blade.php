@extends('layouts.app', ['title' => 'QR codes'])

@section('content')
<style>
    .qr { width:120px; height:120px; background:#fff; border-radius:6px; padding:4px; }
    .qr svg { width:100%; height:100%; display:block; }
    .print-only { display:none; }
    @media print {
        header, .actions, .no-print, .flash { display:none !important; }
        body, .card { background:#fff !important; color:#000 !important; border:0 !important; }
        .print-only { display:block; }
        tr { break-inside:avoid; }
    }
</style>
<h1>QR codes</h1>
<p class="lead">Every QR checkpoint placed in a published map of a mall. A printed code holds only its random address; scanning it opens the Smart Mall app, which asks this service where the code hangs. <b>Retire</b> a code that was taken down, <b>New address</b> when a printed code must stop working (print it again). Codes are created when a map with QR checkpoints is published for a mall.</p>
<p class="no-print"><button type="button" onclick="window.print()">Print this page</button></p>

@forelse ($rows as $mallRef => $checkpoints)
    <div class="card">
        <h2>Mall <code>{{ $mallRef }}</code></h2>
        <table>
            <thead><tr><th>Code</th><th>Where</th><th>QR</th><th></th></tr></thead>
            <tbody>
            @foreach ($checkpoints as $row)
                @php($checkpoint = $row['checkpoint'])
                <tr>
                    <td>
                        <div><b>{{ $checkpoint->checkpoint_code }}</b></div>
                        <div>
                            <span class="badge {{ $checkpoint->isActive() ? 'ok' : 'bad' }}">{{ $checkpoint->isActive() ? 'active' : 'retired' }}</span>
                            <span class="badge {{ $row['on_map'] ? 'ok' : 'off' }}">{{ $row['on_map'] ? 'on the published map, floor '.$row['floor_code'] : 'not on the published map' }}</span>
                        </div>
                    </td>
                    <td>
                        <div>{{ $checkpoint->label ?? '—' }}</div>
                        <div class="muted" style="font-size:12px"><code>{{ $row['url'] }}</code></div>
                    </td>
                    <td><div class="qr" data-qr="{{ $row['url'] }}" role="img" aria-label="QR code for {{ $checkpoint->checkpoint_code }}"></div></td>
                    <td class="actions">
                        @if ($checkpoint->isActive())
                            <form method="post" action="{{ route('admin.checkpoints.retire', $checkpoint) }}" class="inline">@csrf<button type="submit">Retire</button></form>
                        @else
                            <form method="post" action="{{ route('admin.checkpoints.reactivate', $checkpoint) }}" class="inline">@csrf<button type="submit">Reactivate</button></form>
                        @endif
                        <form method="post" action="{{ route('admin.checkpoints.replace', $checkpoint) }}" class="inline" onsubmit="return confirm('Give {{ $checkpoint->checkpoint_code }} a new address? Codes printed with the old one stop working.')">@csrf<button class="danger" type="submit">New address</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="card"><p class="muted">No QR codes yet. Give a map a mall on the <a href="{{ route('admin.maps.index') }}">Maps</a> page and publish it; its QR checkpoints appear here.</p></div>
@endforelse

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
    document.querySelectorAll('[data-qr]').forEach(function (box) {
        if (typeof qrcode !== 'function') {
            box.textContent = 'QR library not loaded';
            return;
        }
        var qr = qrcode(0, 'M');
        qr.addData(box.dataset.qr);
        qr.make();
        box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    });
</script>
@endsection
