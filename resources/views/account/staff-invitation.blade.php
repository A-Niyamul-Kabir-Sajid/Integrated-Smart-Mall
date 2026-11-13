<x-layouts.customer :title="__('Staff invitation')">
    <div class="mx-auto max-w-lg px-4 py-10">
        <h1 class="text-2xl font-semibold">{{ __('Staff invitation') }}</h1>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if ($invitation === null)
            <p class="mt-6 rounded-xl border border-slate-200 bg-white p-6 text-slate-700">{{ __('This invitation cannot be used. Ask the shop owner for a new one.') }}</p>
        @else
            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
                <p>{{ __('You are invited to work at :shop as :function.', ['shop' => $invitation->shop->name, 'function' => $invitation->staff_function->label()]) }}</p>

                @if ($emailMatches)
                    <form method="POST" action="{{ route('staff-invitations.accept', $secret) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 font-medium text-white hover:bg-brand-700">{{ __('Accept the invitation') }}</button>
                    </form>
                @else
                    <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('This invitation was sent to a different email address than the one on your account. Sign in with the invited address to accept it.') }}</p>
                @endif
            </div>
        @endif
    </div>
</x-layouts.customer>
