<?php

namespace App\Modules\ChatbotRag\Services\Assistant;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\DTOs\AssistantReplyData;
use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\ChatbotRag\Enums\AssistantTool;
use App\Modules\ChatbotRag\Models\AssistantInteraction;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps the record of what the assistant was asked and how it ended (Jira ISM-136).
 *
 * Two records with different purposes: the interaction log counts every question without naming
 * anyone, and the audit trail names the administrator who asked for figures or a report draft, and
 * records every refused request. Neither stores an answer, and the audit trail never stores the
 * question. A failure to write either is reported and never costs the visitor the answer.
 */
class InteractionLog
{
    public const UNANSWERED_LIMIT = 50;

    public function __construct(private AuditLogger $audit) {}

    public function record(Mall $mall, AccessScope $scope, AssistantReplyData $reply): void
    {
        try {
            $keepQuestion = $scope === AccessScope::Public && in_array($reply->outcome, [AnswerOutcome::NoAnswer, AnswerOutcome::LowConfidence], true);

            AssistantInteraction::query()->create([
                'mall_id' => $mall->getKey(),
                'access_scope' => $scope->value,
                'tool' => $reply->tool?->value ?? 'none',
                'outcome' => $reply->outcome->value,
                'language' => $reply->language,
                'driver' => $reply->driver,
                'fallback_reason' => $reply->fallbackReason,
                'refusal_reason' => $reply->refusalReason,
                'question' => $keepQuestion ? Str::limit($reply->question, 500, '') : null,
                'created_at' => now(),
            ]);

            if ($reply->outcome === AnswerOutcome::Refused) {
                $this->audit->record('assistant.request_refused', null, [
                    'scope' => $scope->value, 'reason' => $reply->refusalReason, 'tool' => $reply->tool?->value,
                ], mallId: (string) $mall->getKey());
            } elseif ($scope === AccessScope::MallAdmin && in_array($reply->tool, [AssistantTool::MetricLookup, AssistantTool::ReportDraft], true)) {
                $this->audit->record($reply->tool === AssistantTool::ReportDraft ? 'assistant.report_drafted' : 'assistant.figures_read', null, array_filter([
                    'outcome' => $reply->outcome->value, 'period' => $reply->meta['period'] ?? null, 'month' => $reply->meta['month'] ?? null,
                ]), mallId: (string) $mall->getKey());
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * What the mall's assistant was asked in the latest days: counts by outcome, tool, driver and
     * reason, and the visitors' questions it could not answer well.
     *
     * @return array{days: int, total: int, outcomes: array<string, int>, tools: array<string, int>, drivers: array<string, int>, fallbacks: array<string, int>, refusals: array<string, int>, unanswered: list<array{question: string, outcome: string, language: string, at: CarbonImmutable}>}
     */
    public function summary(User $administrator, Mall $mall, int $days = 30): array
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $since = now()->subDays($days);
        $rows = fn () => AssistantInteraction::query()->where('mall_id', $mall->getKey())->where('created_at', '>=', $since);
        $counts = fn (string $column): array => $rows()->whereNotNull($column)->selectRaw("{$column} as name, count(*) as total")->groupBy($column)->orderByDesc('total')->orderBy($column)
            ->pluck('total', 'name')->map(fn ($total): int => (int) $total)->all();

        return [
            'days' => $days,
            'total' => $rows()->count(),
            'outcomes' => $counts('outcome'),
            'tools' => $counts('tool'),
            'drivers' => $counts('driver'),
            'fallbacks' => $counts('fallback_reason'),
            'refusals' => $counts('refusal_reason'),
            'unanswered' => $rows()->whereNotNull('question')->orderByDesc('created_at')->orderByDesc('interaction_id')->limit(self::UNANSWERED_LIMIT)->get()
                ->map(fn (AssistantInteraction $interaction): array => [
                    'question' => (string) $interaction->question,
                    'outcome' => (string) $interaction->outcome,
                    'language' => (string) $interaction->language,
                    'at' => $interaction->created_at->timezone($mall->timezone),
                ])->all(),
        ];
    }

    /**
     * Deletes interaction records older than the configured number of days.
     */
    public function purgeOld(): int
    {
        return AssistantInteraction::query()->where('created_at', '<', now()->subDays(max(1, (int) config('assistant.interaction_days', 90))))->delete();
    }
}
