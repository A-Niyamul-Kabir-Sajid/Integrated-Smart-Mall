@php($currentRating = (int) old('rating', $own?->rating))

<form method="POST" action="{{ $action }}" class="mt-3 space-y-4 rounded-xl border border-slate-200 bg-white p-5">
    @csrf

    @if ($errors->has('business'))
        <p class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $errors->first('business') }}</p>
    @endif

    <fieldset>
        <legend class="text-sm font-medium">{{ __('Your rating') }}</legend>
        <div class="mt-1 flex flex-wrap gap-3">
            @foreach ([5, 4, 3, 2, 1] as $star)
                <label class="flex items-center gap-1.5 text-sm">
                    <input type="radio" name="rating" value="{{ $star }}" @checked($currentRating === $star) required class="size-4 accent-brand-600">
                    <span>{{ __(':count stars', ['count' => $star]) }}</span>
                </label>
            @endforeach
        </div>
        @error('rating') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
    </fieldset>

    <x-form-field name="title" :label="__('Title (optional)')" :value="$own?->title" maxlength="160" />

    <div>
        <label for="body" class="block text-sm font-medium">{{ __('Your review') }}</label>
        <textarea id="body" name="body" rows="4" required minlength="10" maxlength="2000" @error('body') aria-invalid="true" @enderror class="mt-1 w-full rounded-lg bg-white px-3 py-2 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500">{{ old('body', $own?->body) }}</textarea>
        @error('body') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
    </div>

    @if ($isListing && ! $own?->isVerified())
        <x-form-field name="purchase_code" :label="__('Purchase code (optional)')" :hint="__('Got a code with your receipt? Enter it to show a verified purchase badge. We check it on our side.')" autocomplete="off" class="font-mono text-sm" />
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-500">
            @if ($own)
                {{ __('You can edit this review until :date.', ['date' => \App\Modules\ReviewsModeration\Services\ReviewPolicy::editDeadline($own)->timezone('Asia/Dhaka')->format('d M Y')]) }}
            @else
                {{ __('Be honest and polite. Reviews are public.') }}
            @endif
        </p>
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 font-medium text-white hover:bg-brand-700">{{ $own ? __('Update review') : __('Publish review') }}</button>
    </div>
</form>
