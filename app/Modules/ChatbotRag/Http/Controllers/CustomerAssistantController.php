<?php

namespace App\Modules\ChatbotRag\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Http\Requests\AskAssistantRequest;
use App\Modules\ChatbotRag\Services\Assistant\AssistantService;
use App\Modules\ChatbotRag\Services\Assistant\ConversationPresenter;
use App\Modules\ChatbotRag\Services\Assistant\ConversationService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The visitors' assistant of one mall. Open to everyone, signed in or not; a conversation is always
 * a public one here, whoever asks and whatever the question says.
 */
class CustomerAssistantController extends Controller
{
    public function __construct(private ConversationService $conversations) {}

    public function show(Request $request, Mall $publicMall, ConversationPresenter $presenter): View
    {
        $conversation = $this->conversations->current($publicMall, AccessScope::Public, $request->user(), $request->session());

        return view('customer.assistant.show', [
            'mall' => $publicMall,
            'messages' => $presenter->present($conversation, $publicMall, AccessScope::Public),
            'hasConversation' => $conversation !== null,
            'keptUntil' => $conversation?->expires_at->timezone($publicMall->timezone),
            'usesModel' => config('assistant.driver') !== 'template' && filled(config('services.gemini.key')),
            'suggestions' => [
                __('Where can I get a cold drink?'),
                __('Which shops are open now?'),
                __('Where is the prayer room?'),
                __('What does parking cost?'),
            ],
        ]);
    }

    public function store(AskAssistantRequest $request, Mall $publicMall, AssistantService $assistant): RedirectResponse
    {
        $conversation = $this->conversations->currentOrNew($publicMall, AccessScope::Public, $request->user(), $request->session());
        $assistant->ask($conversation, $publicMall, $request->user(), $request->validated('message'));

        return redirect()->to(route('assistant.show', ['publicMall' => $publicMall->slug]).'#latest');
    }

    public function destroy(Request $request, Mall $publicMall): RedirectResponse
    {
        $conversation = $this->conversations->current($publicMall, AccessScope::Public, $request->user(), $request->session());

        if ($conversation !== null) {
            $this->conversations->clear($conversation, $request->session());
        }

        return redirect()->route('assistant.show', ['publicMall' => $publicMall->slug])->with('status', __('The conversation was deleted.'));
    }
}
