<?php

namespace App\Modules\ChatbotRag\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\DocumentStatus;
use App\Modules\ChatbotRag\Enums\SourceKind;
use App\Modules\ChatbotRag\Http\Requests\KnowledgeDocumentRequest;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeService;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeSyncService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The knowledge documents of one mall: what the assistant may quote. Routes require administer-mall
 * for the mall in the URL; a document of another mall is not found through it.
 */
class KnowledgeAdminController extends Controller
{
    public function __construct(private KnowledgeService $knowledge) {}

    public function index(Request $request, Mall $mall): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(SourceKind::class)],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'origin' => ['nullable', Rule::in(['written', 'captured'])],
        ]);

        return view('mall-admin.knowledge.index', [
            'mall' => $mall,
            'documents' => $this->knowledge->listForMall($request->user(), $mall, $filters),
            'filters' => $filters,
            'kinds' => SourceKind::cases(),
        ]);
    }

    public function create(Mall $mall): View
    {
        return view('mall-admin.knowledge.form', $this->formData($mall, new KnowledgeDocument(['source_kind' => SourceKind::Faq, 'access_scope' => AccessScope::Public]), ''));
    }

    public function store(KnowledgeDocumentRequest $request, Mall $mall): RedirectResponse
    {
        $document = $this->knowledge->create($request->user(), $mall, $request->document());

        return redirect()->route('assistant.admin.knowledge.show', [$mall, $document])->with('status', __('":title" was added. The assistant can quote it now.', ['title' => $document->title]));
    }

    public function show(Mall $mall, KnowledgeDocument $knowledgeDocument): View
    {
        $knowledgeDocument->load(['revisions' => fn ($revisions) => $revisions->withCount('chunks'), 'currentRevision.chunks']);

        return view('mall-admin.knowledge.show', ['mall' => $mall, 'document' => $knowledgeDocument]);
    }

    public function edit(Mall $mall, KnowledgeDocument $knowledgeDocument): View
    {
        abort_if($knowledgeDocument->isSystemManaged(), 404);

        return view('mall-admin.knowledge.form', $this->formData($mall, $knowledgeDocument, (string) $knowledgeDocument->currentRevision?->content_text));
    }

    public function update(KnowledgeDocumentRequest $request, Mall $mall, KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $before = $knowledgeDocument->current_revision_id;
        $document = $this->knowledge->revise($request->user(), $knowledgeDocument, $request->document());

        return redirect()->route('assistant.admin.knowledge.show', [$mall, $document])->with('status', $document->current_revision_id === $before
            ? __('The document was saved. The text did not change, so no new revision was captured.')
            : __('The document was saved as a new revision. The assistant quotes the new text from now on.'));
    }

    public function withdraw(Request $request, Mall $mall, KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $this->knowledge->withdraw($request->user(), $knowledgeDocument);

        return back()->with('status', __('":title" was withdrawn. The assistant no longer quotes it.', ['title' => $knowledgeDocument->title]));
    }

    public function restore(Request $request, Mall $mall, KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $this->knowledge->restore($request->user(), $knowledgeDocument);

        return back()->with('status', __('":title" is active again.', ['title' => $knowledgeDocument->title]));
    }

    public function destroy(Request $request, Mall $mall, KnowledgeDocument $knowledgeDocument): RedirectResponse
    {
        $title = $knowledgeDocument->title;
        $this->knowledge->delete($request->user(), $knowledgeDocument);

        return redirect()->route('assistant.admin.knowledge.index', $mall)->with('status', __('":title" was deleted.', ['title' => $title]));
    }

    public function sync(Mall $mall, KnowledgeSyncService $sync): RedirectResponse
    {
        $result = $sync->syncMall($mall);

        return back()->with('status', __('Mall records read: :created added, :updated updated, :unchanged unchanged, :withdrawn withdrawn.', $result));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Mall $mall, KnowledgeDocument $document, string $content): array
    {
        return [
            'mall' => $mall,
            'document' => $document,
            'content' => $content,
            'kinds' => SourceKind::writable(),
            'scopes' => AccessScope::cases(),
        ];
    }
}
