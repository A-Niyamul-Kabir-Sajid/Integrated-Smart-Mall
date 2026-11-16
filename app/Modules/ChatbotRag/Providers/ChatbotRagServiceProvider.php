<?php

namespace App\Modules\ChatbotRag\Providers;

use App\Core\Modules\ModuleServiceProvider;
use App\Modules\ChatbotRag\Adapters\GeminiAnswerGenerator;
use App\Modules\ChatbotRag\Console\EvaluateAssistant;
use App\Modules\ChatbotRag\Console\PurgeAssistantData;
use App\Modules\ChatbotRag\Console\SyncKnowledge;
use App\Modules\ChatbotRag\Contracts\AnswerGenerator;
use App\Modules\ChatbotRag\Contracts\ChunkSearch;
use App\Modules\ChatbotRag\Contracts\PublicOfferSource;
use App\Modules\ChatbotRag\Listeners\RefreshShopKnowledge;
use App\Modules\ChatbotRag\Models\KnowledgeDocument;
use App\Modules\ChatbotRag\Services\Generation\FallbackAnswerGenerator;
use App\Modules\ChatbotRag\Services\Generation\GeneratedAnswerValidator;
use App\Modules\ChatbotRag\Services\Generation\TemplateAnswerGenerator;
use App\Modules\ChatbotRag\Services\Knowledge\NoPublicOffers;
use App\Modules\ChatbotRag\Services\Retrieval\PortableChunkSearch;
use App\Modules\ChatbotRag\Services\Retrieval\PostgresChunkSearch;
use App\Modules\ChatbotRag\Services\Retrieval\QueryExpander;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;
use App\Modules\MallShopDirectory\Events\ShopStatusChanged;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Registers ChatbotRag: the knowledge documents, their retrieval and the assistant that answers
 * from them (Jira EPIC-13, decision D-018).
 */
class ChatbotRagServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [
        ShopStatusChanged::class => [RefreshShopKnowledge::class],
    ];

    public function register(): void
    {
        $this->app->singleton(TextAnalyzer::class);
        $this->app->singleton(QueryExpander::class);

        // PostgreSQL searches with full-text and trigram indexes; other databases get a portable scan.
        $this->app->bind(ChunkSearch::class, fn (Application $app): ChunkSearch => DB::connection()->getDriverName() === 'pgsql'
            ? $app->make(PostgresChunkSearch::class)
            : $app->make(PortableChunkSearch::class));

        // The module that owns promotions binds the real source; without it profiles carry no offers.
        $this->app->bindIf(PublicOfferSource::class, NoPublicOffers::class);

        // Answers are written by Gemini when a key is configured, and by the template driver
        // otherwise and whenever the model cannot answer (decision D-018).
        $this->app->bind(AnswerGenerator::class, function (Application $app): AnswerGenerator {
            $driver = (string) config('assistant.driver', 'auto');
            $useModel = $driver === 'gemini' || ($driver === 'auto' && filled(config('services.gemini.key')));

            return new FallbackAnswerGenerator(
                $useModel ? $app->make(GeminiAnswerGenerator::class) : null,
                $app->make(TemplateAnswerGenerator::class),
                $app->make(GeneratedAnswerValidator::class),
            );
        });
    }

    public function boot(): void
    {
        parent::boot();

        // A document in an administration URL must belong to the mall in the same URL; otherwise 404.
        Route::bind('knowledgeDocument', function (string $id, RoutingRoute $route): KnowledgeDocument {
            abort_unless(Str::isUuid($id), 404);
            $mall = $route->parameter('mall');

            return KnowledgeDocument::query()->whereKey($id)->where('mall_id', $mall instanceof Mall ? $mall->getKey() : $mall)->firstOrFail();
        });

        $this->commands([SyncKnowledge::class, PurgeAssistantData::class, EvaluateAssistant::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SyncKnowledge::class)->hourly()->withoutOverlapping(30)->onOneServer();
            $schedule->command(PurgeAssistantData::class)->dailyAt('03:45')->onOneServer();
        });

        RateLimiter::for('assistant-admin', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Questions cost a search and possibly a model call, so they are limited per visitor.
        RateLimiter::for('assistant', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
