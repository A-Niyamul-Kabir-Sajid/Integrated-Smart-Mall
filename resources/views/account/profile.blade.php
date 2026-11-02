<x-layouts.customer :title="__('Edit profile')">
    <x-auth-card :title="__('Edit profile')" :subtitle="__('Changing your email or mobile number needs your current password.')">
        <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4" novalidate>
            @csrf
            @method('PUT')
            <x-form-field name="display_name" :label="__('Full name')" :value="$user->display_name" autocomplete="name" required maxlength="120" />
            <x-form-field name="email" type="email" :label="__('Email')" :value="$user->email" autocomplete="email" />
            <x-form-field name="phone" type="tel" :label="__('Mobile number')" :value="$user->phone" autocomplete="tel" placeholder="01712345678" />
            <x-form-field name="current_password" type="password" :label="__('Current password')" autocomplete="current-password"
                :hint="__('Only needed when you change your email or mobile number.')" />

            <div class="flex gap-3">
                <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('Save changes') }}</button>
                <a href="{{ route('account.show') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 font-medium text-slate-700 hover:bg-slate-50">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-auth-card>
</x-layouts.customer>
