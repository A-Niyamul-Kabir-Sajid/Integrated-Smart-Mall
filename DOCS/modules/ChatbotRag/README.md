# ChatbotRag

## Responsibility

Customer retrieval, recommendations and authorized assistant integration. Source folder: [app/Modules/ChatbotRag](../../../app/Modules/ChatbotRag/).

Status: implemented for EPIC-13 (decision D-018): knowledge documents and captured mall records, retrieval with one access filter (PostgreSQL full-text search and `pg_trgm`), the visitors' assistant with a Gemini driver and a template fallback, the administrators' assistant, audit and activity, and an evaluation set. Embeddings (`CHUNK_EMBEDDING`) and indoor routes (MapsNavigation) are not built.

## Implementation (EPIC-13, decision D-018)

### Tables

| Table | Holds | Notes |
|---|---|---|
| `knowledge_documents` | One document per source: kind (`faq`, `mall_guide`, `admin_report`, `shop_profile`, `product_listing`), scope (`public`, `mall_admin`), status, `current_revision_id` | Locator `system:...` marks a captured record; `admin_report` is always `mall_admin` |
| `document_revisions` | Immutable text of each revision with a content hash | Updating a revision throws; a changed text is a new revision |
| `knowledge_chunks` | The pieces the assistant quotes: `heading`, `chunk_text`, `search_text`, `token_count` | On PostgreSQL also a generated `search_vector` (GIN) and a trigram index on `search_text` |
| `conversations`, `chat_messages` | A visitor's conversation (server session or account) or an administrator's | `expires_at` from the retention; `meta` holds tool, outcome, filters and directions |
| `message_citations`, `message_recommendations` | The chunks an answer quoted and the offers it recommended | Re-checked each time a conversation is shown |
| `assistant_interactions` | How each question ended (scope, tool, outcome, language, driver, fallback or refusal reason) | Outside the ERD; no account, session or conversation; question text only for a visitor's unanswered or warned question, masked |

### Pages and commands

| Route or command | Who | Does |
|---|---|---|
| `GET/POST/DELETE /malls/{mall}/assistant` | anyone (20 questions a minute) | Visitors' assistant in English or Bangla |
| `GET /malls/{mall}/assistant/go/{type}/{id}` (type `shop`, `facility` or `listing`) | anyone | Re-checks the destination and redirects to the shop page or the facility finder |
| `/admin/malls/{mall}/assistant` (+ `/activity`, `/knowledge/...`) | `administer-mall` (60 a minute) | Administrators' assistant, activity page, knowledge screens |
| `php artisan assistant:sync-knowledge {mall?}` | scheduled hourly | Captures shop profiles, offers and the floor guide; idempotent |
| `php artisan assistant:purge` | scheduled daily 03:45 | Deletes expired conversations and old interaction records |
| `php artisan assistant:evaluate {mall=niagara-city-mall} {--json}` | developers | Runs the 37 evaluation cases with the template driver |

### Configuration

`config/assistant.php` (driver `auto`/`gemini`/`template`, thresholds, retention, limits) and `config/services.php` `gemini` (`GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_BASE_URL`, `GEMINI_TIMEOUT`). Without a key every answer is written by the templates.

### Contracts used and offered

- Reads `CatalogueLookup` (ProductsCatalogue), `DirectoryLookup`, `DirectoryService`, `FacilityService` and `OpeningStatusService` (MallShopDirectory), `ReviewSummaryLookup` (ReviewsModeration), and `MallDashboardService`, `SourceData`, `ReportMetric` and `MonthlyReportService::draftForMall()` (AnalyticsReports); writes audit entries with `AuditLogger` (SharedPlatform). It never uses another module's `Http` classes, ShopOwnerOperations or MallOwnerAdmin.
- Offers `ChatbotRag\Contracts\PublicOfferSource`, implemented by ShopOwnerOperations for public promotions (default: none).

### Evaluation result (2026-11-17, seeded demonstration mall, PostgreSQL)

37 of 37 cases passed (grounding 10, catalogue 5, directory 4, uncertainty 3, refusal 5, access 3, administrators 7); retrieval hit@1 1.0, hit@3 1.0, MRR 1.0 over 11 questions with a known source; no quote outside its source, no offer outside the catalogue, no source shown to the wrong reader. The set is small and fixed: it guards against regressions, it does not measure real visitors.

### Limits

Lexical retrieval only (no embeddings); Bangla is matched through a fixed word list and Bangla text; the template driver quotes sources in their own language; the Gemini driver is tested against a faked API only; directions stop at the shop page or facility finder until EPIC-05; administrators' screens are English only; thresholds, retention and limits are defaults to confirm (D-018).

Manual checks: [guide 37](../../../guide/TestByYourself/37_assistant_knowledge_and_retrieval.md), [guide 38](../../../guide/TestByYourself/38_customer_assistant.md), [guide 39](../../../guide/TestByYourself/39_admin_assistant_safety_and_evaluation.md).

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 06: customer chatbot recommendations](../../references/functional/DOC/modules/06_customer_chatbot_recommendations/GUIDE.md), including its completion checks.
- [Catalogue section 11: mall owner analytics decision support](../../references/functional/DOC/modules/11_mall_owner_analytics_decision_support/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 08-chatbot-rag: [overview](../../references/erd/DOC/05-modules/08-chatbot-rag/README.md), [business rules](../../references/erd/DOC/05-modules/08-chatbot-rag/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/08-chatbot-rag/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/08-chatbot-rag/ERD.md).

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: ProductsCatalogue, MallShopDirectory, ReviewsModeration, MapsNavigation, AnalyticsReports. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-09. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.
