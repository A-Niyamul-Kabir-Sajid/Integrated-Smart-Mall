<x-layouts.customer :title="__('Staff').' · '.$shop->name">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="{{ route('workspace.dashboard', $shop) }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; {{ $shop->name }}</a>
        <h1 class="mt-2 text-2xl font-semibold">{{ __('Staff') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Managers use every area except staff and profile. Catalogue editors manage stock. Cashiers handle orders, sales, returns and customer messages.') }}</p>

        @error('business') <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</p> @enderror

        @if (session('invitation_link'))
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                <p class="font-medium">{{ __('Invitation link (shown only now)') }}</p>
                <p class="mt-1 break-all font-mono text-xs">{{ session('invitation_link') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('workspace.staff.invite', $shop) }}" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
            @csrf
            <x-form-field name="email" type="email" :label="__('Invite by email')" required maxlength="254" />
            <div>
                <label for="staff_function" class="block text-sm font-medium">{{ __('Work function') }}</label>
                <select id="staff_function" name="staff_function" class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">
                    @foreach ($functions as $function)
                        <option value="{{ $function->value }}" @selected(old('staff_function') === $function->value)>{{ $function->label() }}</option>
                    @endforeach
                </select>
                @error('staff_function') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 font-medium text-white hover:bg-brand-700">{{ __('Send invitation') }}</button>
        </form>

        <section class="mt-8" aria-labelledby="members-heading">
            <h2 id="members-heading" class="text-lg font-semibold">{{ __('Staff members') }}</h2>
            @if ($members->isEmpty())
                <p class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No staff yet.') }}</p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    @foreach ($members as $member)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <span><span class="font-medium">{{ $member->user->display_name }}</span> <span class="text-sm text-slate-500">· {{ $member->user->email }}</span></span>
                            <span class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('workspace.staff.function', [$shop, $member]) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <label for="function-{{ $member->getKey() }}" class="sr-only">{{ __('Work function of :name', ['name' => $member->user->display_name]) }}</label>
                                    <select id="function-{{ $member->getKey() }}" name="staff_function" class="rounded-lg bg-white px-2 py-1.5 text-sm ring-1 ring-slate-300">
                                        @foreach ($functions as $function)
                                            <option value="{{ $function->value }}" @selected($member->staffFunction?->staff_function === $function)>{{ $function->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50">{{ __('Change') }}</button>
                                </form>
                                <form method="POST" action="{{ route('workspace.staff.remove', [$shop, $member]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-300 px-3 py-1.5 text-sm font-medium text-rose-800 hover:bg-rose-50" aria-label="{{ __('Remove :name', ['name' => $member->user->display_name]) }}">{{ __('Remove') }}</button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mt-8" aria-labelledby="invitations-heading">
            <h2 id="invitations-heading" class="text-lg font-semibold">{{ __('Pending invitations') }}</h2>
            @if ($invitations->isEmpty())
                <p class="mt-3 text-sm text-slate-600">{{ __('No pending invitations.') }}</p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                    @foreach ($invitations as $invitation)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <span><span class="font-medium">{{ $invitation->email }}</span> <span class="text-sm text-slate-500">· {{ $invitation->staff_function->label() }} · {{ $invitation->expires_at->isPast() ? __('Expired') : __('Expires :date', ['date' => $invitation->expires_at->timezone('Asia/Dhaka')->format('d M Y')]) }}</span></span>
                            <form method="POST" action="{{ route('workspace.staff.invitations.revoke', [$shop, $invitation]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50" aria-label="{{ __('Revoke the invitation to :email', ['email' => $invitation->email]) }}">{{ __('Revoke') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.customer>
