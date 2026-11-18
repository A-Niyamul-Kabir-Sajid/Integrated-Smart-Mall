<?php

namespace App\Modules\ChatbotRag\Console;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Contracts\AnswerGenerator;
use App\Modules\ChatbotRag\Services\Evaluation\DemoEvaluationSet;
use App\Modules\ChatbotRag\Services\Evaluation\EvaluationRunner;
use App\Modules\ChatbotRag\Services\Generation\FallbackAnswerGenerator;
use App\Modules\ChatbotRag\Services\Generation\GeneratedAnswerValidator;
use App\Modules\ChatbotRag\Services\Generation\TemplateAnswerGenerator;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;

/**
 * Runs the evaluation questions of the demonstration mall and prints what passed. It always answers
 * with the template driver, so a run costs nothing, calls no external service and gives the same
 * result every time; it stores nothing. Exits with an error when a case fails.
 */
class EvaluateAssistant extends Command
{
    protected $signature = 'assistant:evaluate {mall='.DemoEvaluationSet::MALL_SLUG.' : Slug of the seeded demonstration mall} {--json : Print the full result as JSON}';

    protected $description = 'Check the assistant against the evaluation questions of the demonstration mall';

    public function handle(Application $app): int
    {
        $mall = Mall::query()->active()->where('slug', $this->argument('mall'))->first();

        if ($mall === null) {
            $this->error('No active mall with that slug. Seed the demonstration data first: php artisan migrate:fresh --seed');

            return self::FAILURE;
        }

        $app->bind(AnswerGenerator::class, fn (Application $app): AnswerGenerator => new FallbackAnswerGenerator(
            null, $app->make(TemplateAnswerGenerator::class), $app->make(GeneratedAnswerValidator::class),
        ));

        $administrator = User::query()->whereHas('mallMemberships', fn ($memberships) => $memberships->active()
            ->where('mall_id', $mall->getKey())
            ->whereHas('role', fn ($role) => $role->where('code', RoleCode::MallAdmin->value)))->orderBy('user_id')->first();

        $result = $app->make(EvaluationRunner::class)->run($mall, DemoEvaluationSet::cases(), $administrator);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $result['summary']['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->table(['Case', 'Group', 'Asked as', 'Result', 'Outcome', 'Problems'], array_map(fn (array $case): array => [
            $case['id'], $case['group'], $case['scope'], strtoupper($case['status']), $case['outcome'] ?? '-', implode(' ', $case['problems']),
        ], $result['cases']));

        $summary = $result['summary'];
        $this->line("Cases: {$summary['total']}, passed {$summary['passed']}, failed {$summary['failed']}, skipped {$summary['skipped']}.");

        foreach ($summary['groups'] as $group => $counts) {
            $this->line("  {$group}: {$counts['passed']} passed, {$counts['failed']} failed, {$counts['skipped']} skipped");
        }

        $retrieval = $summary['retrieval'];
        $this->line("Retrieval over {$retrieval['questions']} questions with a known source: hit@1 ".($retrieval['hit_at_1'] ?? '-').', hit@3 '.($retrieval['hit_at_3'] ?? '-').', MRR '.($retrieval['mrr'] ?? '-').'.');
        $this->line("Quotes not found in their source: {$summary['unsupported_citations']}. Offers not in the catalogue: {$summary['fabricated_offers']}. Sources shown to the wrong reader: {$summary['access_violations']}.");

        return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
