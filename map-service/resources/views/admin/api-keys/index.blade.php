@extends('layouts.app', ['title' => 'API keys'])

@section('content')
<style>textarea { background:var(--bg); color:var(--text); border:1px solid var(--line); border-radius:6px; padding:8px 10px; font:13px ui-monospace,Consolas,monospace; width:100%; } form.origins { margin-top:8px; display:grid; gap:6px; max-width:320px; }</style>
<h1>API keys</h1>
<p class="lead">Other apps, such as the Smart Mall app, call <code>{{ url('/api/v1') }}</code> with one of these keys in the header <code>Authorization: Bearer &lt;key&gt;</code>. Only a fingerprint of each key is stored, so a key can be copied only right after it is created.</p>

@if (session('new_key'))
    <div class="card" style="border-color:var(--accent)">
        <h2>Your new key</h2>
        <span class="key">{{ session('new_key') }}</span>
        <p class="muted">Put it in the other app's <code>.env</code>, for example <code>MAP_SERVICE_KEY={{ session('new_key') }}</code>. It will not be shown again.</p>
    </div>
@endif

<div class="card">
    <h2>Create a key</h2>
    <form method="post" action="{{ route('admin.api-keys.store') }}" class="grid">
        @csrf
        <label>Name (who uses it) <input type="text" name="name" value="{{ old('name', 'Smart Mall app') }}" maxlength="80" required></label>
        <label>Expires after (days, empty = never) <input type="number" name="expires_in_days" value="{{ old('expires_in_days') }}" min="1" max="3650"></label>
        <label>Sites that may show the embedded map (one origin per line) <textarea name="allowed_origins" rows="2" placeholder="http://127.0.0.1:8000">{{ old('allowed_origins', 'http://127.0.0.1:8000') }}</textarea></label>
        <div>
            @foreach ($abilities as $ability => $description)
                <label class="check" title="{{ $description }}"><input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, old('abilities', ['maps:read']), true))> <code>{{ $ability }}</code></label>
            @endforeach
        </div>
        <div><button class="primary" type="submit">Create key</button></div>
    </form>
    <p class="muted" style="margin-bottom:0">@foreach ($abilities as $ability => $description)<code>{{ $ability }}</code> {{ $description }}. @endforeach</p>
</div>

<div class="card">
    <h2>Keys</h2>
    @if ($keys->isEmpty())
        <p class="muted">None yet.</p>
    @else
        <table>
            <thead><tr><th>Name</th><th>Status</th><th>Abilities</th><th></th></tr></thead>
            <tbody>
            @foreach ($keys as $key)
                <tr>
                    <td>
                        <div>{{ $key->name }}</div>
                        <div class="muted" style="font-size:12px"><code>{{ $key->prefix }}…</code> · created {{ $key->created_at->format('Y-m-d') }} · last used {{ $key->last_used_at?->diffForHumans() ?? 'never' }}</div>
                    </td>
                    <td>
                        @if ($key->revoked_at)
                            <span class="badge bad">revoked</span>
                        @elseif ($key->expires_at && $key->expires_at->isPast())
                            <span class="badge bad">expired</span>
                        @else
                            <span class="badge ok">active</span>@if ($key->expires_at)<div class="muted" style="font-size:12px">until {{ $key->expires_at->format('Y-m-d') }}</div>@endif
                        @endif
                    </td>
                    <td>
                        @foreach ($key->abilities as $ability)<code>{{ $ability }}</code> @endforeach
                        @unless ($key->revoked_at)
                            <form method="post" action="{{ route('admin.api-keys.origins', $key) }}" class="origins">
                                @csrf @method('patch')
                                <label>Embedded map allowed on
                                    <textarea name="allowed_origins" rows="2" aria-label="Embedded map origins of {{ $key->name }}">{{ implode(PHP_EOL, $key->origins()) }}</textarea>
                                </label>
                                <button type="submit">Save origins</button>
                            </form>
                        @endunless
                    </td>
                    <td class="actions">
                        @if (! $key->revoked_at)
                            <form method="post" action="{{ route('admin.api-keys.revoke', $key) }}" class="inline" onsubmit="return confirm('Revoke {{ addslashes($key->name) }}? Apps using it stop working.')">
                                @csrf
                                <button class="danger" type="submit">Revoke</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
