<?php

namespace App\Modules\ChatbotRag\Services\Assistant;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Contracts\AnswerGenerator;
use App\Modules\ChatbotRag\DTOs\AssistantReplyData;
use App\Modules\ChatbotRag\DTOs\GeneratedAnswerData;
use App\Modules\ChatbotRag\DTOs\GenerationRequestData;
use App\Modules\ChatbotRag\DTOs\SourceSnippetData;
use App\Modules\ChatbotRag\DTOs\ToolResultData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Enums\AssistantTool;
use App\Modules\ChatbotRag\Models\ChatMessage;
use App\Modules\ChatbotRag\Models\Conversation;
use App\Modules\ChatbotRag\Services\Assistant\Tools\FacilityTool;
use App\Modules\ChatbotRag\Services\Assistant\Tools\KnowledgeTool;
use App\Modules\ChatbotRag\Services\Assistant\Tools\MetricTool;
use App\Modules\ChatbotRag\Services\Assistant\Tools\OpenShopsTool;
use App\Modules\ChatbotRag\Services\Assistant\Tools\ProductTool;
use App\Modules\ChatbotRag\Services\Assistant\Tools\ReportDraftTool;
use App\Modules\ChatbotRag\Services\Generation\TemplateAnswerGenerator;
use App\Modules\ChatbotRag\Services\Text\LanguageDetector;
use App\Modules\ChatbotRag\Services\Text\PersonalDataMasker;
use App\Modules\MallShopDirectory\Enums\FacilityType;
use App\Modules\MallShopDirectory\Models\Facility;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Services\FacilityService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Answers one question (Jira ISM-131, decision D-018).
 *
 * The steps never change: remove personal details, detect the language, let the guard refuse an
 * attempt to change the rules, route the question to one tool, check the tool against the allowlist
 * for this kind of conversation, run it, and write the answer from what it found. Text about the
 * mall comes only from the tool's sources: quoted by the template driver, or written by the model
 * from the public sources and checked afterwards. When nothing relevant is found the assistant says
 * so and names the information desk.
 */
class AssistantService
{
    public const NOT_PERMITTED = 'not_permitted';

    private const EXCERPT_MAX = 500;

    public function __construct(
        private InputGuard $guard,
        private ToolRouter $router,
        private PersonalDataMasker $masker,
        private LanguageDetector $languages,
        private KnowledgeTool $knowledge,
        private ProductTool $products,
        private FacilityTool $facilities,
        private OpenShopsTool $openShops,
        private AnswerGenerator $generator,
        private TemplateAnswerGenerator $template,
        private NavigationHandoff $handoff,
        private FacilityService $facilityDirectory,
        private ConversationService $conversations,
        private MetricTool $metrics,
        private ReportDraftTool $reportDrafts,
        private InteractionLog $log,
    ) {}

    /**
     * Answers the question and stores question and answer in the conversation.
     */
    public function ask(Conversation $conversation, Mall $mall, ?User $user, string $message): ChatMessage
    {
        $reply = $this->respond($mall, $conversation->access_scope, $user, $message);
        $answer = $this->conversations->record($conversation, $reply);
        $this->log->record($mall, $conversation->access_scope, $reply);

        return $answer;
    }

    /**
     * Answers the question without storing anything.
     *
     * @param  AccessScope  $scope  The kind of conversation, decided by the page and the account, never by the message
     */
    public function respond(Mall $mall, AccessScope $scope, ?User $user, string $message): AssistantReplyData
    {
        $question = trim($this->masker->mask($message));
        $language = $this->languages->detect($message, app()->getLocale());
        $refusal = $this->guard->inspect($message);

        if ($refusal !== null) {
            return $this->refuse($question, $language, null, $refusal, __('I cannot help with that request. I can help with shops, products, facilities and information about this mall.', [], $language));
        }

        // What is searched for: the question without markup and without the masking placeholders.
        $asked = $this->masker->withoutPlaceholders(strip_tags($question));
        $tool = $this->router->route($asked, $scope);

        if (! $tool->allowedIn($scope)) {
            return $this->refuse($question, $language, $tool, self::NOT_PERMITTED, $this->notPermittedText($tool, $language), $this->notPermittedMeta($tool, $mall, $scope));
        }

        $result = $this->run($tool, $mall, $scope, $user, $asked, $language);

        if ($result->outcome === AnswerOutcome::NoAnswer) {
            foreach ($this->alternativesTo($tool) as $alternative) {
                $other = $this->run($alternative, $mall, $scope, $user, $asked, $language);

                if ($other->outcome !== AnswerOutcome::NoAnswer) {
                    [$tool, $result] = [$alternative, $other];

                    break;
                }
            }
        }

        return $this->compose($mall, $scope, $question, $language, $tool, $result);
    }

    private function run(AssistantTool $tool, Mall $mall, AccessScope $scope, ?User $user, string $question, string $language): ToolResultData
    {
        return match ($tool) {
            AssistantTool::SmallTalk => new ToolResultData(AnswerOutcome::Answered, fixedText: $this->introduction($mall, $scope, $language)),
            AssistantTool::HumanHelp => $this->informationDesk($mall, $language, AnswerOutcome::Answered),
            AssistantTool::KnowledgeSearch => $this->knowledge->run($mall, $scope, $question),
            AssistantTool::ProductSearch => $this->products->run($mall, $question, $language),
            AssistantTool::FacilityLookup => $this->facilities->run($mall, $question, $language),
            AssistantTool::OpenShops => $this->openShops->run($mall, $language),
            AssistantTool::MetricLookup => $user === null ? ToolResultData::nothing() : $this->metrics->run($mall, $user, $question),
            AssistantTool::ReportDraft => $user === null ? ToolResultData::nothing() : $this->reportDrafts->run($mall, $user, $question),
            default => ToolResultData::nothing(),
        };
    }

    /**
     * What to try when a tool finds nothing: a product request may be a question about the mall
     * ("do you have Wi-Fi?"), and a question about the mall may be a product request ("umbrellas?").
     *
     * @return list<AssistantTool>
     */
    private function alternativesTo(AssistantTool $tool): array
    {
        return match ($tool) {
            AssistantTool::ProductSearch, AssistantTool::FacilityLookup => [AssistantTool::KnowledgeSearch],
            AssistantTool::KnowledgeSearch => [AssistantTool::ProductSearch],
            default => [],
        };
    }

    private function compose(Mall $mall, AccessScope $scope, string $question, string $language, AssistantTool $tool, ToolResultData $result): AssistantReplyData
    {
        $outcome = $result->outcome;
        $answer = null;
        $cited = [];

        if ($result->fixedText !== null) {
            $text = $result->fixedText;
        } elseif ($outcome === AnswerOutcome::NoAnswer) {
            $result = $this->nothingFound($mall, $scope, $language);
            $text = (string) $result->fixedText;
        } else {
            $sources = $this->readableBy($scope, $result->sources);
            $answer = $this->generatorFor($scope)->generate(new GenerationRequestData(
                question: $question,
                language: $language,
                mallName: $mall->name,
                sources: $sources,
                shape: $result->shape,
                lead: $result->lead,
                uncertain: $outcome === AnswerOutcome::LowConfidence,
            ));

            if ($answer->answerable) {
                $text = $answer->text;
                $cited = array_values(array_filter($sources, fn (SourceSnippetData $source): bool => in_array($source->number, $answer->cited, true)));
            } else {
                $outcome = AnswerOutcome::NoAnswer;
                $desk = $this->nothingFound($mall, $scope, $language);
                $result = new ToolResultData(AnswerOutcome::NoAnswer, handoffs: $desk->handoffs);
                $text = (string) $desk->fixedText;
            }
        }

        $handoffs = $result->handoffs;
        $linkedShops = array_column(array_filter($handoffs, fn (array $handoff): bool => $handoff['type'] === NavigationHandoff::SHOP), 'id');
        array_push($handoffs, ...$this->handoff->shopsMentionedIn($mall, $text, $linkedShops));

        return new AssistantReplyData(
            question: $question,
            text: $text,
            outcome: $outcome,
            tool: $tool,
            language: $language,
            driver: $answer?->driver ?? GeneratedAnswerData::TEMPLATE,
            modelLabel: $answer?->modelLabel,
            fallbackReason: $answer?->fallbackReason,
            refusalReason: $outcome === AnswerOutcome::Refused ? ($result->meta['refusal_reason'] ?? null) : null,
            citations: array_map(fn (SourceSnippetData $source): array => [
                'chunk_id' => (string) $source->chunkId,
                'number' => $source->number,
                'score' => $source->score,
                'excerpt' => Str::limit($this->template->excerpt($question, $source->text), self::EXCERPT_MAX),
            ], array_values(array_filter($cited, fn (SourceSnippetData $source): bool => $source->chunkId !== null))),
            recommendations: $outcome->gaveAnAnswer() ? $result->recommendations : [],
            meta: array_filter([
                'tool' => $tool->value,
                'outcome' => $outcome->value,
                'language' => $language,
                'driver' => $answer?->driver ?? GeneratedAnswerData::TEMPLATE,
                'fallback_reason' => $answer?->fallbackReason,
                'live_sources' => array_map(
                    fn (SourceSnippetData $source): array => ['number' => $source->number, 'title' => $source->title, 'text' => $source->text],
                    array_values(array_filter($cited, fn (SourceSnippetData $source): bool => $source->chunkId === null)),
                ),
                'handoffs' => $handoffs,
                ...$result->meta,
            ], fn (mixed $value): bool => $value !== null && $value !== []),
        );
    }

    /**
     * The model writes for the public only, and only from public sources. The administrators'
     * assistant always uses the template driver, so nothing of theirs leaves the application.
     */
    private function generatorFor(AccessScope $scope): AnswerGenerator
    {
        return $scope === AccessScope::Public ? $this->generator : $this->template;
    }

    /**
     * The sources this kind of conversation may be shown. Retrieval already filters by scope; a
     * source that slipped through is dropped here and reported, never shown.
     *
     * @param  list<SourceSnippetData>  $sources
     * @return list<SourceSnippetData>
     */
    private function readableBy(AccessScope $scope, array $sources): array
    {
        $readable = array_values(array_filter($sources, fn (SourceSnippetData $source): bool => in_array($source->scope->value, $scope->readableScopes(), true)));

        if (count($readable) !== count($sources)) {
            report('A source outside the conversation scope reached the answer step and was dropped.');
        }

        return $readable;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function refuse(string $question, string $language, ?AssistantTool $tool, string $reason, string $text, array $meta = []): AssistantReplyData
    {
        return new AssistantReplyData(
            question: $question,
            text: $text,
            outcome: AnswerOutcome::Refused,
            tool: $tool,
            language: $language,
            driver: GeneratedAnswerData::TEMPLATE,
            refusalReason: $reason,
            meta: array_filter(['tool' => $tool?->value, 'outcome' => AnswerOutcome::Refused->value, 'language' => $language, 'driver' => GeneratedAnswerData::TEMPLATE, 'refusal_reason' => $reason, ...$meta]),
        );
    }

    private function notPermittedText(AssistantTool $tool, string $language): string
    {
        return $tool === AssistantTool::AdminAction
            ? __('I cannot change anything from this chat. Use the page for that task in the mall workspace; I can answer questions and show figures.', [], $language)
            : __('Figures about sales, orders, complaints and other business records are available only to the mall\'s administrators in their workspace. I can help with shops, products, facilities and information about this mall.', [], $language);
    }

    /**
     * @return array<string, mixed>
     */
    private function notPermittedMeta(AssistantTool $tool, Mall $mall, AccessScope $scope): array
    {
        if ($tool !== AssistantTool::AdminAction || $scope !== AccessScope::MallAdmin || ! Route::has('mall-admin.dashboard')) {
            return [];
        }

        return ['links' => [['label' => 'Mall workspace', 'url' => route('mall-admin.dashboard', $mall)]]];
    }

    private function introduction(Mall $mall, AccessScope $scope, string $language): string
    {
        return $scope === AccessScope::MallAdmin
            ? __('I am the assistant of :mall for its administrators. Ask me for a figure of the metric catalogue for a period (sales, orders, complaints, ratings, searches), for a draft of a monthly report, or about the mall\'s documents. I answer only from recorded figures and documents, and I cannot change anything from this chat.', ['mall' => $mall->name], $language)
            : __('I am the assistant of :mall. Ask me where a shop or facility is, which shops sell a product and what it costs, which shops are open now, or about parking, opening hours and other mall information. I answer only from the mall\'s own information and tell you when I do not know.', ['mall' => $mall->name], $language);
    }

    /**
     * The reply when nothing relevant was found: a visitor is sent to the information desk, an
     * administrator is told what the assistant can report.
     */
    private function nothingFound(Mall $mall, AccessScope $scope, string $language): ToolResultData
    {
        return $scope === AccessScope::MallAdmin
            ? new ToolResultData(AnswerOutcome::NoAnswer, fixedText: __('I could not find that in the mall\'s documents, and it is not a figure of the metric catalogue, so I will not guess. I can report sales, orders, returns, reviews and ratings, promotions, complaints, searches and rankings of shops for a period, for example "complaints last month".', [], $language))
            : $this->informationDesk($mall, $language, AnswerOutcome::NoAnswer);
    }

    /**
     * The reply that sends the visitor to a person: the information desk, with directions when the
     * mall has one. Used when a person is asked for and when the assistant has no answer.
     */
    private function informationDesk(Mall $mall, string $language, AnswerOutcome $outcome): ToolResultData
    {
        $desk = $this->facilityDirectory->find($mall, ['type' => FacilityType::Information->value, 'open_only' => true])->first();
        $opening = $outcome === AnswerOutcome::NoAnswer
            ? __('I could not find that in the mall\'s information, so I will not guess.', [], $language)
            : __('I am an automated assistant and cannot connect you to a person from here.', [], $language);

        if (! $desk instanceof Facility) {
            return new ToolResultData($outcome, fixedText: $opening.' '.__('Please ask the mall staff.', [], $language));
        }

        return new ToolResultData(
            outcome: $outcome,
            fixedText: $opening.' '.__('The staff at :desk (:where) can help you.', ['desk' => $desk->name, 'where' => $desk->floor->name.(filled($desk->location_note) ? ', '.$desk->location_note : '')], $language),
            handoffs: [$this->handoff->forFacility($desk)],
        );
    }
}
