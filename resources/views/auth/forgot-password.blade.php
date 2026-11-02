<x-layouts.customer :title="__('Forgot your password?')">
    <x-auth-card :title="__('Forgot your password?')" :subtitle="__('Enter the email on your account and we will send you a link to choose a new password.')">
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4" novalidate>
            @csrf
            <x-form-field name="email" type="email" :label="__('Email')" autocomplete="email" required autofocus />
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Send reset link') }}</button>
        </form>
        <p class="mt-4 text-xs text-slate-500">{{ __('Signed up with a mobile number only? Ask the mall information desk to help you regain access.') }}</p>

        <x-slot:footer>
            <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline">{{ __('Back to sign in') }}</a>
        </x-slot:footer>
    </x-auth-card>
</x-layouts.customer>
