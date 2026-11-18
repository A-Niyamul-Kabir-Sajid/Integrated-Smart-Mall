<?php

namespace App\Modules\ChatbotRag\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Http\Requests\AskAssistantRequest;
use App\Modules\ChatbotRag\Services\Assistant\AssistantService;
use App\Modules\ChatbotRag\Services\Assistant\ConversationPresenter;
use App\Modules\ChatbotRag\Services\Assistant\ConversationService;
use App\Modules\ChatbotRag\Services\Assistant\InteractionLog;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The administrators' assistant of one mall and its activity page. Routes require administer-mall
 * for the mall in the URL: that check, not anything typed into the chat, is what makes a
 * conversation an administrators' conversation. Each administrator has their own.
 */
class AdminAssistantController extends Controller
{
    public function __construct(private ConversationService $conversations) {}

    public function show(Request $request, Mall $mall, ConversationPresenter $presenter): View
    {
        $conversation = $this->conversations->current($mall, AccessScope::MallAdmin, $request->user(), $request->session());

        return view('mall-admin.assistant.show', [
            'mall' => $mall,
            'messages' => $presenter->present($conversation, $mall, AccessScope::MallAdmin),
            'hasConversation' => $conversation !== null,
            'suggestions' => [
                __('What were the sales last month?'),
                __('Which shops received the most complaints this month?'),
                __('What did visitors search for and not find in the last 30 days?'),
                __('Draft the monthly report for last month'),
            ],
        ]);
    }

    public function store(AskAssistantRequest $request, Mall $mall, AssistantService $assistant): RedirectResponse
    {
        $conversation = $this->conversations->currentOrNew($mall, AccessScope::MallAdmin, $request->user(), $request->session());
        $assistant->ask($conversation, $mall, $request->user(), $request->validated('message'));

        return redirect()->to(route('assistant.admin.show', $mall).'#latest');
    }

    public function destroy(Request $request, Mall $mall): RedirectResponse
    {
        $conversation = $this->conversations->current($mall, AccessScope::MallAdmin, $request->user(), $request->session());

        if ($conversation !== null) {
            $this->conversations->clear($conversation, $request->session());
        }

        return redirect()->route('assistant.admin.show', $mall)->with('status', __('The conversation was deleted.'));
    }

    public function activity(Request $request, Mall $mall, InteractionLog $log): View
    {
        return view('mall-admin.assistant.activity', [
            'mall' => $mall,
            'summary' => $log->summary($request->user(), $mall),
        ]);
    }
}
