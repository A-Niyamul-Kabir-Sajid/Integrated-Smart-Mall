<x-layouts.customer :title="__('Sign in')">
    <x-auth-card :title="__('Sign in')" :subtitle="__('Customers, shop staff and mall administrators use the same account.')">
        <form method="POST" action="{{ route('login.store') }}" class="space-y-4" novalidate>
            @csrf
            <x-form-field name="login" :label="__('Email or mobile number')" autocomplete="username" required autofocus />
            <x-form-field name="password" type="password" :label="__('Password')" autocomplete="current-password" required />
            <div class="flex items-center justify-between gap-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 accent-brand-600">
                {{ __('Keep me signed in') }}
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Forgot password?') }}</a>
            </div>
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700">
                {{ __('Sign in') }}
            </button>
        </form>

        <a href="{{ url('/') }}" class="mt-4 block rounded-lg border border-slate-300 px-4 py-2.5 text-center font-medium text-slate-700 hover:bg-slate-50">
            {{ __('Continue as guest') }}
        </a>

        <x-slot:footer>
            {{ __('New here?') }} <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:underline">{{ __('Create an account') }}</a>
        </x-slot:footer>
    </x-auth-card>
</x-layouts.customer>
