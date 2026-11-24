@php
    $labels = [
        'answered' => __('Answered'), 'low_confidence' => __('Answered with a warning'), 'no_answer' => __('Not answered'), 'refused' => __('Refused'),
        'small_talk' => __('Greeting or help'), 'human_help' => __('Asked for a person'), 'knowledge_search' => __('Documents'), 'product_search' => __('Product finder'),
        'open_shops' => __('Shops open now'), 'facility_lookup' => __('Facilities'), 'metric_lookup' => __('Figures'), 'report_draft' => __('Report draft'), 'admin_action' => __('Request to change something'), 'none' => __('Refused before routing'),
        'template' => __('Built-in templates'), 'gemini' => __('AI model (Gemini)'),
        'missing_key' => __('No API key'), 'rate_limited' => __('Rate limit reached'), 'api_error' => __('API error'), 'connection_failed' => __('Connection failed'), 'no_output' => __('Model gave no output'),
        'malformed_output' => __('Model output unreadable'), 'invalid_output' => __('Model answer failed the source check'), 'restricted_context' => __('Context was not public'),
        'prompt_injection' => __('Attempt to change the assistant\'s rules'), 'private_data' => __('Request for other people\'s data'), 'not_permitted' => __('Tool not permitted in that conversation'), 'not_in_scope' => __('Figure belongs to someone else'),
    ];
    $sections = [
        ['title' => __('How questions ended'), 'rows' => $summary['outcomes']],
        ['title' => __('What was asked for'), 'rows' => $summary['tools']],
        ['title' => __('Who wrote the answers'), 'rows' => $summary['drivers']],
        ['title' => __('Why the templates answered instead of the model'), 'rows' => $summary['fallbacks']],
        ['title' => __('Why requests were refused'), 'rows' => $summary['refusals']],
    ];
@endphp

<x-layouts.customer :title="__('Assistant activity').' · '.$mall->name" :mall="$mall">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <p class="text-sm text-slate-500">{{ __('Mall administration') }}</p>
        <h1 class="text-2xl font-semibold">{{ $mall->name }} · {{ __('Assistant activity') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('Questions to the assistant in the last :days days: :total. No account, session or conversation is recorded here, so a question cannot be traced to a person.', ['days' => $summary['days'], 'total' => $summary['total']]) }}</p>
        <ul class="mt-3 flex flex-wrap gap-3 text-sm">
            <li><a href="{{ route('assistant.admin.show', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Assistant') }}</a></li>
            <li><a href="{{ route('assistant.admin.knowledge.index', $mall) }}" class="font-medium text-brand-700 hover:underline">{{ __('Assistant knowledge') }}</a></li>
        </ul>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            @foreach ($sections as $section)
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold">{{ $section['title'] }}</h2>
                    @if ($section['rows'] === [])
                        <p class="mt-2 text-sm text-slate-500">{{ __('Nothing recorded.') }}</p>
                    @else
                        <table class="mt-2 w-full text-sm">
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($section['rows'] as $name => $count)
                                    <tr>
                                        <th scope="row" class="py-1.5 text-left font-normal">{{ $labels[$name] ?? $name }}</th>
                                        <td class="py-1.5 text-right font-medium tabular-nums">{{ $count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endforeach
        </div>

        <section class="mt-8" aria-labelledby="unanswered-heading">
            <h2 id="unanswered-heading" class="text-lg font-semibold">{{ __('Questions the assistant could not answer well') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Visitors\' questions that got no answer or only a warned one, newest first, with email addresses and phone numbers removed. Add a document that answers them under Assistant knowledge.') }}</p>
            @if ($summary['unanswered'] === [])
                <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-slate-600">{{ __('No unanswered question in this period.') }}</p>
            @else
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">{{ __('Question') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Result') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Language') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('When') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($summary['unanswered'] as $row)
                                <tr>
                                    <td class="px-4 py-2 break-words">{{ $row['question'] }}</td>
                                    <td class="px-4 py-2">{{ $labels[$row['outcome']] ?? $row['outcome'] }}</td>
                                    <td class="px-4 py-2">{{ $row['language'] === 'bn' ? __('Bangla') : __('English') }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap">{{ $row['at']->format('d M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts.customer>
