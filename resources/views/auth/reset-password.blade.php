<x-layouts.customer :title="__('Choose a new password')">
    <x-auth-card :title="__('Choose a new password')" :subtitle="__('Every device signed in to your account will be signed out.')">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-form-field name="email" type="email" :label="__('Email')" :value="$email" autocomplete="email" required />
            <x-form-field name="password" type="password" :label="__('New password')" autocomplete="new-password" required
                :hint="__('At least 8 characters with letters and numbers.')" />
            <x-form-field name="password_confirmation" type="password" :label="__('Confirm new password')" autocomplete="new-password" required />
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Reset password') }}</button>
        </form>
    </x-auth-card>
</x-layouts.customer>
