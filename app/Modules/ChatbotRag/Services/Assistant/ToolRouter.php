<?php

namespace App\Modules\ChatbotRag\Services\Assistant;

use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\AssistantTool;
use App\Modules\ChatbotRag\Services\Assistant\Tools\FacilityTool;
use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;

/**
 * Decides which tool a question is for. Fixed rules do this, in a fixed order, so the same question
 * always goes to the same tool and no model chooses what the assistant does.
 *
 * Routing is not permission. A question about sales is routed to the metric tool whoever asks;
 * whether that tool may run is decided afterwards by AssistantTool::allowedIn().
 */
class ToolRouter
{
    private const SMALL_TALK = '/^(hi|hello|hey|hiya|salam|assalamu alaikum|as-salamu alaykum|good (morning|afternoon|evening)|thanks|thank you|thank u|ok|okay|help|bye|goodbye|who are you|what can you do|what do you do|how can you help( me)?|হাই|হ্যালো|সালাম|আসসালামু আলাইকুম|ধন্যবাদ|সাহায্য)[\s.!?।]*$/u';

    private const HUMAN_HELP = '/\b(talk|speak|chat)\s+(to|with)\s+(a |an |the |some)?(human|person|someone|somebody|staff|agent|representative|manager|operator)\b|\b(real person|live agent|human agent|customer (service|care|support))\b|\b(file|make|lodge|raise) a complaint\b|মানুষের সাথে কথা/u';

    private const REPORT_DRAFT = '/\b(draft|write|prepare|generate|create|compose|summari[sz]e|make)\b[^.?!]{0,40}\breport\b|\bmonthly report\b|\breport for\b/u';

    /**
     * Words that ask for business figures, whoever is asking.
     */
    private const BUSINESS_FIGURES = '/\b(sales|revenue|turnover|earning|earnings|income|profit|profits|analytics|dashboard|statistics|kpi|kpis)\b|\bhow (many|much)\b[^.?!]{0,25}\b(orders|complaints|customers|visitors|sales|sold|earn|earned|made)\b/u';

    /**
     * Further words that name a figure of the metric catalogue; only read in the administrators' assistant,
     * because a visitor may use them innocently ("best rated coffee").
     */
    private const ADMIN_FIGURES = '/\b(orders?|reservations?|complaints?|reviews?|ratings?|returns?|refunds?|discounts?|coupons?|redemptions?|quality flags?|flags?|page views?|searches|search demand|unmet|segments?|tenants?|active shops|top shops?|best shops?|worst shops?|units sold|completion rate|forecast|demand by)\b/u';

    private const ADMIN_ACTION = '/^\s*(please\s+|can you\s+|could you\s+|kindly\s+)?(suspend|approve|reject|delete|remove|archive|ban|block|activate|deactivate|publish|unpublish|assign|resolve|close|reopen|refund|cancel|edit|update|change|set|add|create a|create an|rename|move|hide|restore|grant|revoke)\b/u';

    private const OPEN_NOW = '/\b(open (right )?now|open today|currently open|still open|open at the moment|what(\'s| is) open|which (shops?|stores?) (is|are) open)\b|এখন খোলা/u';

    private const SHOPPING = '/\b(buy|purchase|price|prices|cost|costs|cheap|cheaper|cheapest|expensive|affordable|budget|sell|sells|selling|sold|in stock|available|availability|looking for|where can i (get|find|buy)|where (do|can) (i|we|you) (get|find|buy)|do you (have|sell)|does (anyone|any shop|any store) sell|i (want|need)|show me|find me|recommend|suggest|top rated|best rated|under \d|below \d)\b|দাম|কিনতে|কিনব|সস্তা|টাকার মধ্যে|বিক্রি|কোথা\x{09AF}\x{09BC}\s+(পাব|পাবো|পাও\x{09AF}\x{09BC}া)|পাও\x{09AF}\x{09BC}া যা\x{09AF}\x{09BC}/u';

    public function __construct(private TextAnalyzer $analyzer, private FacilityTool $facilities) {}

    public function route(string $message, AccessScope $scope): AssistantTool
    {
        $text = $this->analyzer->normalise($message);

        if (preg_match(self::SMALL_TALK, $text) === 1) {
            return AssistantTool::SmallTalk;
        }

        if (preg_match(self::HUMAN_HELP, $text) === 1) {
            return AssistantTool::HumanHelp;
        }

        if ($scope === AccessScope::MallAdmin && preg_match(self::ADMIN_ACTION, $text) === 1 && preg_match(self::REPORT_DRAFT, $text) !== 1) {
            return AssistantTool::AdminAction;
        }

        if (preg_match(self::REPORT_DRAFT, $text) === 1 && ($scope === AccessScope::MallAdmin || preg_match(self::BUSINESS_FIGURES, $text) === 1 || str_contains($text, 'monthly report'))) {
            return AssistantTool::ReportDraft;
        }

        if (preg_match(self::BUSINESS_FIGURES, $text) === 1 || ($scope === AccessScope::MallAdmin && preg_match(self::ADMIN_FIGURES, $text) === 1)) {
            return AssistantTool::MetricLookup;
        }

        if ($this->facilities->mentionsFacilities($text)) {
            return AssistantTool::FacilityLookup;
        }

        if (preg_match(self::OPEN_NOW, $text) === 1) {
            return AssistantTool::OpenShops;
        }

        if (preg_match(self::SHOPPING, $text) === 1) {
            return AssistantTool::ProductSearch;
        }

        return AssistantTool::KnowledgeSearch;
    }
}
