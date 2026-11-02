@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 font-sans text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:rounded focus:bg-white focus:px-3 focus:py-2">
        {{ __('Skip to content') }}
    </a>

    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold">
                <span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white" aria-hidden="true">32</span>
                <span>{{ config('app.name') }}</span>
            </a>

            <div class="ml-auto flex items-center gap-2 text-sm">
                @auth
                    <a href="{{ route('account.show') }}" class="rounded-lg px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100">{{ auth()->user()->display_name }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-1.5 font-medium text-brand-700 hover:bg-brand-50">{{ __('Sign out') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-1.5 font-medium text-brand-700 hover:bg-brand-50">{{ __('Sign in') }}</a>
                    <a href="{{ route('register') }}" class="hidden rounded-lg px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100 sm:inline-block">{{ __('Create an account') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main">
        @if (session('status'))
            <div class="mx-auto mt-4 max-w-6xl px-4">
                <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</p>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
