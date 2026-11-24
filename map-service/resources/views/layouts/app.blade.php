<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Map service' }} · ISM Map Service</title>
    <style>
        :root { --bg:#0f141c; --panel:#161d29; --panel-2:#1d2635; --line:#2a3546; --text:#e6ebf2; --muted:#93a0b4; --accent:#4f8cff; --accent-2:#2f6fe8; --danger:#e5484d; --ok:#30a46c; --warn:#ffcf8a; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        header { display:flex; align-items:center; gap:18px; padding:12px 24px; background:var(--panel); border-bottom:1px solid var(--line); flex-wrap:wrap; }
        header strong { font-size:15px; }
        header nav { display:flex; gap:4px; flex:1; }
        header nav a { color:var(--muted); text-decoration:none; padding:6px 10px; border-radius:6px; }
        header nav a.on, header nav a:hover { color:var(--text); background:var(--panel-2); }
        main { max-width:1100px; margin:0 auto; padding:24px 16px 48px; }
        h1 { font-size:22px; margin:0 0 6px; }
        h2 { font-size:15px; margin:0 0 12px; }
        p.lead { color:var(--muted); margin:0 0 20px; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:12px; padding:18px; margin-bottom:18px; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); vertical-align:middle; }
        th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
        td.actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
        input[type=text], input[type=email], input[type=password], input[type=number], select { background:var(--bg); color:var(--text); border:1px solid var(--line); border-radius:6px; padding:8px 10px; font:inherit; width:100%; }
        label { display:flex; flex-direction:column; gap:5px; color:var(--muted); font-size:13px; }
        label.check { flex-direction:row; align-items:center; gap:8px; color:var(--text); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; align-items:end; }
        button, .button { display:inline-flex; align-items:center; gap:6px; background:var(--panel-2); color:var(--text); border:1px solid var(--line); border-radius:6px; padding:7px 12px; font:inherit; cursor:pointer; text-decoration:none; }
        button:hover, .button:hover { border-color:var(--accent); }
        .primary { background:var(--accent-2); border-color:var(--accent-2); }
        .primary:hover { background:var(--accent); }
        .danger { color:#ffb4b6; border-color:#5a2a2d; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; background:var(--panel-2); border:1px solid var(--line); }
        .badge.ok { color:#9be4bd; border-color:#24563d; }
        .badge.off { color:var(--muted); }
        .badge.bad { color:#ffb4b6; border-color:#5a2a2d; }
        .flash { background:#173a2a; border:1px solid #24563d; color:#c8f2da; padding:10px 14px; border-radius:8px; margin-bottom:16px; }
        .errors { background:#3a1719; border:1px solid #5a2a2d; color:#ffd0d1; padding:10px 14px; border-radius:8px; margin-bottom:16px; }
        .muted { color:var(--muted); }
        code, .key { font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-size:13px; background:var(--bg); padding:2px 6px; border-radius:4px; word-break:break-all; }
        .key { display:block; padding:12px; font-size:15px; border:1px dashed var(--accent); margin:10px 0; user-select:all; }
        form.inline { display:inline; }
        @media (max-width:640px) { td.actions { justify-content:flex-start; } th:nth-child(3), td:nth-child(3) { display:none; } }
    </style>
</head>
<body>
@auth
<header>
    <strong>ISM Map Service</strong>
    <nav>
        <a href="{{ route('admin.maps.index') }}" class="{{ request()->routeIs('admin.maps.*') ? 'on' : '' }}">Maps</a>
        <a href="{{ route('admin.api-keys.index') }}" class="{{ request()->routeIs('admin.api-keys.*') ? 'on' : '' }}">API keys</a>
    </nav>
    <span class="muted">{{ auth()->user()->email }}</span>
    <form method="post" action="{{ route('logout') }}" class="inline">@csrf<button>Sign out</button></form>
</header>
@endauth
<main>
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif
    @yield('content')
</main>
</body>
</html>
