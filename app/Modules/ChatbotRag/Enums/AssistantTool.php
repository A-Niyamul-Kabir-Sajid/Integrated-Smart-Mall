<?php

namespace App\Modules\ChatbotRag\Enums;

/**
 * Everything the assistant can do with a question. A question is routed to exactly one tool, and a
 * tool runs only when allowedIn() permits it for the kind of conversation: this list is the
 * allowlist. The kind of conversation comes from the page and the signed-in account, never from
 * what the message says, so no wording can reach a tool of the administrators' assistant.
 */
enum AssistantTool: string
{
    /** A greeting or a question about what the assistant does: a fixed text. */
    case SmallTalk = 'small_talk';

    /** The visitor wants a person: the information desk is named. */
    case HumanHelp = 'human_help';

    /** Search of the knowledge documents the reader may see. */
    case KnowledgeSearch = 'knowledge_search';

    /** A product request turned into validated catalogue filters. */
    case ProductSearch = 'product_search';

    /** The shops open at this moment, read from the directory. */
    case OpenShops = 'open_shops';

    /** Facilities by type, read from the directory. */
    case FacilityLookup = 'facility_lookup';

    /** A predefined figure of the mall's metric catalogue. */
    case MetricLookup = 'metric_lookup';

    /** A draft of a monthly report built from those figures; nothing is stored. */
    case ReportDraft = 'report_draft';

    /** A request to change something. Never run from chat, for anyone. */
    case AdminAction = 'admin_action';

    public function allowedIn(AccessScope $scope): bool
    {
        return match ($this) {
            self::MetricLookup, self::ReportDraft => $scope === AccessScope::MallAdmin,
            self::AdminAction => false,
            default => true,
        };
    }
}
