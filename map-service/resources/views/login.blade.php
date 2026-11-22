@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
<div class="card" style="max-width:420px; margin:40px auto">
    <h1>ISM Map Service</h1>
    <p class="lead">Sign in to import maps, place shop signs and advertisements, and manage API keys.</p>
    <form method="post" action="{{ url('/login') }}" style="display:flex; flex-direction:column; gap:12px">
        @csrf
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
        <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
        <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button class="primary" type="submit">Sign in</button>
    </form>
</div>
@endsection
