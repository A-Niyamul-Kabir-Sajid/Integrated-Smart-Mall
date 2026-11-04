<x-layouts.customer :title="__('Change password')">
    <x-auth-card :title="__('Change password')" :subtitle="__('Other devices signed in to your account will be signed out.')">
        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4" novalidate>
            @csrf
            @method('PUT')
            <x-form-field name="current_password" type="password" :label="__('Current password')" autocomplete="current-password" required />
            <x-form-field name="password" type="password" :label="__('New password')" autocomplete="new-password" required
                :hint="__('At least 8 characters with letters and numbers.')" />
            <x-form-field name="password_confirmation" type="password" :label="__('Confirm new password')" autocomplete="new-password" required />
            <div class="flex gap-3">
                <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Change password') }}</button>
                <a href="{{ route('account.show') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 font-medium text-slate-700 hover:bg-slate-50">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-auth-card>
</x-layouts.customer>
