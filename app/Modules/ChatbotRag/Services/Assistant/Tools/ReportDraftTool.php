<?php

namespace App\Modules\ChatbotRag\Services\Assistant\Tools;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AnalyticsReports\Services\Reporting\MonthlyReportService;
use App\Modules\ChatbotRag\DTOs\ToolResultData;
use App\Modules\ChatbotRag\Enums\AnswerOutcome;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\Ability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Drafts the monthly report of the mall for an administrator (Jira ISM-135). The draft is the
 * narrative AnalyticsReports writes from the month's figures, each sentence with the metrics it
 * rests on; this tool adds no sentence of its own and stores nothing.
 *
 * Keeping the report is a separate, explicit step: the answer carries a button that posts the month
 * to the monthly-report screen, where the usual access check and audit apply. Chat text alone never
 * stores or changes anything.
 */
class ReportDraftTool
{
    public function __construct(private MonthlyReportService $reports, private PeriodParser $periods) {}

    public function run(Mall $mall, User $administrator, string $question): ToolResultData
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $month = $this->periods->month(mb_strtolower($question), $mall);

        try {
            $draft = $this->reports->draftForMall($administrator, $mall, $month);
        } catch (BusinessRuleViolation $violation) {
            return new ToolResultData(AnswerOutcome::NoAnswer, fixedText: $violation->getMessage());
        }

        $name = CarbonImmutable::parse($month.'-01')->format('F Y');
        $lines = [__('Draft of the monthly report for :month (not saved):', ['month' => $name])];

        foreach ($draft['narrative'] as $number => $sentence) {
            $lines[] = ($number + 1).'. '.$sentence['text'].' ['.implode(', ', $sentence['metrics']).']';
        }

        $lines[] = $draft['partial']
            ? __('The month is not over: the figures run from :from to :to.', ['from' => $draft['period']['from'], 'to' => $draft['period']['to']])
            : __('The figures cover :from to :to.', ['from' => $draft['period']['from'], 'to' => $draft['period']['to']]);
        $lines[] = __('Every sentence is built from the recorded figures named in brackets. Nothing has been stored.');

        return new ToolResultData(
            outcome: AnswerOutcome::Answered,
            fixedText: implode("\n", $lines),
            meta: [
                'month' => $month,
                'actions' => Route::has('mall-admin.analytics.reports.store') ? [[
                    'label' => __('Generate and store this report'),
                    'url' => route('mall-admin.analytics.reports.store', $mall),
                    'fields' => ['month' => $month],
                    'hint' => __('Stores a snapshot under Monthly reports.'),
                ]] : [],
                'links' => Route::has('mall-admin.analytics.reports.index') ? [['label' => 'Monthly reports', 'url' => route('mall-admin.analytics.reports.index', $mall)]] : [],
            ],
        );
    }
}
