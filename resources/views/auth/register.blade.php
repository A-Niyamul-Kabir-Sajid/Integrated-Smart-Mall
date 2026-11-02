<x-layouts.customer :title="__('Create an account')">
    <x-auth-card :title="__('Create an account')" :subtitle="__('Save favourite shops and leave reviews. Browsing and directions work without an account.')">
        <form method="POST" action="{{ route('register.store') }}" class="space-y-4" novalidate>
            @csrf
            <x-form-field name="display_name" :label="__('Full name')" autocomplete="name" required maxlength="120" />
            <x-form-field name="email" type="email" :label="__('Email')" autocomplete="email"
                :hint="__('Use an email or a mobile number; at least one is required.')" />
            <x-form-field name="phone" type="tel" :label="__('Mobile number')" autocomplete="tel" placeholder="01712345678" />
            <x-form-field name="password" type="password" :label="__('Password')" autocomplete="new-password" required
                :hint="__('At least 8 characters with letters and numbers.')" />
            <x-form-field name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" required />

            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700">
                {{ __('Create account') }}
            </button>
        </form>
    </x-auth-card>
</x-layouts.customer>
