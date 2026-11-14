<x-layouts.customer :title="__('Mall administration').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <p class="text-sm text-slate-500">{{ __('Mall administration') }}</p>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ $mall->name }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ ucfirst($mall->status->value) }}</span>
        </div>

        <section class="mt-6" aria-labelledby="waiting-heading">
            <h2 id="waiting-heading" class="text-lg font-semibold">{{ __('Waiting for you') }}</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($queues as $queue)
                    <li>
                        <a href="{{ $queue['url'] }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 hover:border-brand-500">
                            <span class="font-medium">{{ $queue['label'] }}</span>
                            <span @class(['rounded-full px-2.5 py-0.5 text-sm font-semibold', 'bg-amber-100 text-amber-900' => $queue['count'] > 0, 'bg-slate-100 text-slate-600' => $queue['count'] === 0])>{{ $queue['count'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="mt-8" aria-labelledby="tenants-heading">
            <h2 id="tenants-heading" class="text-lg font-semibold">{{ __('Tenants and structure') }}</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach (['active' => __('Active shops'), 'draft' => __('Draft shops'), 'closed' => __('Suspended shops'), 'archived' => __('Archived shops')] as $status => $label)
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <dt class="text-xs uppercase text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl font-semibold">{{ $shops[$status] }}</dd>
                    </div>
                @endforeach
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <dt class="text-xs uppercase text-slate-500">{{ __('Active floors') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold">{{ $floors }}</dd>
                </div>
            </dl>
        </section>

        <section class="mt-8" aria-labelledby="tools-heading">
            <h2 id="tools-heading" class="text-lg font-semibold">{{ __('Workspaces') }}</h2>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($tools as $tool)
                    <li>
                        <a href="{{ $tool['url'] }}" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 hover:border-brand-500">
                            <span class="font-medium text-brand-700">{{ $tool['label'] }}</span>
                            <span class="mt-0.5 block text-sm text-slate-600">{{ $tool['hint'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.customer>
