# Security guidance

Read [detailed security and verification guidance](references/functional/DOC/quality/SECURITY_VERIFICATION.md) and the relevant [database business rules](06_DATABASE_GUIDE.md).

Use Laravel authentication, policies/gates, Form Requests, CSRF protection, rate limits and scoped middleware. Enforce shop/mall permissions server-side; browser-supplied roles are not authority. Generic reusable middleware belongs in `app/Core/Middleware/`; domain checks stay with the owning module.

Purchase credentials require trusted purchase evidence, unpredictable or securely signed secrets, expiry/revocation rules, and concurrency-safe redemption. Keep tokens and provider credentials out of logs. Customer RAG must not retrieve private administrative data.

Document consent and retention before collecting real history/camera data. Authorize private report downloads; label synthetic data and simulated integrations. Treat emergency information as approved content, not certified evacuation guidance.

Network firewall/WAF belongs to deployment infrastructure. No Firewall design-pattern folder is needed.

## Implemented baseline (EPIC-01, ISM-146)

| Control | Where | Behaviour |
|---|---|---|
| Security headers | `App\Core\Middleware\SecurityHeaders` (web and api groups) | `nosniff`, `SAMEORIGIN` framing, strict referrer policy, Permissions-Policy allowing camera/geolocation for this origin only; HSTS on HTTPS |
| Rate limits | `SharedPlatformServiceProvider` | `throttle:login` 5/min per login and IP, `throttle:registration` 10/hour per IP, `throttle:password-reset` 3/min per IP, `throttle:search` 60/min per user or IP |
| Passwords | `Password::defaults()` | At least 8 characters with letters and numbers; breached-password check in production |
| Eloquent strictness | Non-production only | Lazy loading and silently discarded attributes throw, so N+1 queries and mass-assignment mistakes surface in development and tests |
| Production guards | Production only | HTTPS URLs forced; `migrate:fresh` and other destructive database commands refused |

## Authentication and authorization conventions

- **Authentication** is Laravel's session guard. Protect pages with the `auth` middleware and guest-only pages (sign-in, registration) with `guest`. The sign-in route is named `login`. Apply the matching `throttle:` limiter to every credential endpoint.
- **Policies** live in the owning module's `Policies/` folder and are attached to the model with Laravel's `#[UsePolicy(...)]` attribute. Role checks that span modules are Gates registered in the AccountsAccess provider.
- **Authorize at the protected operation**: in the Form Request's `authorize()` for writes, or `Gate::authorize()` in the controller. Never decide access from data the browser sends (role names, shop IDs in hidden fields).
- **Scope**: shop and mall permissions come from server-side memberships. When a user asks for a record of a shop or mall they do not belong to, respond 404 so the record's existence is not revealed; respond 403 when the record is in their scope but their role lacks the ability.
- **Validation and errors**: writes use Form Requests; simple read filters may use `$request->validate()`. Domain rules throw `BusinessRuleViolation`. `api/*` and JSON requests always receive JSON errors. Production runs with `APP_DEBUG=false`, so responses never include stack traces. Never log passwords, tokens or credentials.

## Abilities (EPIC-02, ISM-155)

Modules check access by ability name, never by role: `Gate::allows(Ability::ManageShop, $shop)` or `->can('manage-shop', 'shop')` on a route. The names live in `App\Modules\SharedPlatform\Enums\Ability`; AccountsAccess defines them from roles and active memberships.

| Ability | Granted to | Argument |
|---|---|---|
| `administer-platform` | `platform_admin` | — |
| `administer-mall` | `mall_admin` with an active membership of that mall; platform admins | `Mall` |
| `manage-shop` | `shop_owner` of that shop; platform admins | `Shop` |
| `work-in-shop` | `shop_owner` or `shop_staff` of that shop; platform admins | `Shop` |

Suspended or anonymised accounts hold no ability, and `EnsureAccountIsActive` (web group) signs them out on their next request. Revoked memberships grant nothing. `tests/Feature/Modules/AccountsAccess/AbilitiesTest.php` holds the full role-by-ability matrix, including denial for another mall or shop.

## Session and password security (EPIC-02, ISM-159)

| Event | Behaviour |
|---|---|
| Sign-in | Session id regenerated; only active accounts; `throttle:login` |
| Sign-out | Session invalidated and CSRF token regenerated |
| Password change (`/account/password`) | Current password required; `throttle:password-change` (5/min per user); remember-me token rotated; every other session deleted, the current one kept |
| Password reset (`/forgot-password`) | Same answer for known and unknown emails; `throttle:password-reset` (3/min per IP) plus the broker's 60-second per-account delay; tokens expire after 60 minutes and work once; all sessions deleted |
| Account suspended or anonymised | Signed out on the next request (`EnsureAccountIsActive`) |
| Any password change or reset | `account.password_changed` / `account.password_reset` audited (no password data) and a `PasswordChanged` notice emailed |

Session cookies are HttpOnly and SameSite=Lax (framework defaults) and live for `SESSION_LIFETIME` minutes. Production must set `SESSION_SECURE_COOKIE=true` so the cookie only travels over HTTPS. Accounts registered with a mobile number only cannot use the email reset; until an SMS reset exists, the information desk helps them.

## Assistant (EPIC-13, decision D-018)

| Control | Where | Behaviour |
|---|---|---|
| One access filter | `ChatbotRag\Services\Retrieval\RetrievableChunks::query()` | Every chunk read is limited to the mall, active documents, the current revision and the conversation's scopes: `public` for visitors, `public` and `mall_admin` for that mall's administrators. The scope comes from the route and the account (`can:administer-mall,mall`), never from the message |
| Model context | `GeminiAnswerGenerator`, `FallbackAnswerGenerator` | Only the question and retrieved `public` passages are sent; a non-public passage makes the driver refuse to call (`restricted_context`). The administrators' assistant never calls a model. Requests use `store: false` |
| API key | `config/services.php` `gemini.key` from `GEMINI_API_KEY` | Sent only in the `x-goog-api-key` header; logs carry a reason code (`missing_key`, `rate_limited`, `api_error`, ...), never the key, the prompt or the answer. Tests use `Http::preventStrayRequests()` and fakes |
| Untrusted text | `InputGuard`, `GeneratedAnswerValidator` | Attempts to change the assistant's rules and requests for other people's data are refused before retrieval; documents are quoted, never obeyed; a model answer with an unknown source, no source, a number not in the sources, the wrong language or too much text is replaced by the template answer |
| Tool allow-list | `AssistantTool::allowedIn()` | Visitors: documents, products, facilities, open shops. Administrators: also figures and report drafts. Actions (`admin_action`) are never allowed; shop-private figures such as stock are refused |
| Personal data | `PersonalDataMasker` | Email addresses and phone-like numbers are masked before a question is stored, searched or sent |
| Rate limits | `ChatbotRagServiceProvider` | `throttle:assistant` 20/min, `throttle:assistant-admin` 60/min, per account or IP; a model rate limit starts a 60-second cool-down on the template driver |
| Audit and records | `AuditLogger`, `assistant_interactions` | `assistant.request_refused`, `assistant.figures_read`, `assistant.report_drafted` and `knowledge.document_*` are audited without question text; interaction records hold no account, session or conversation |
| Retention | `assistant:purge` (daily) | Visitors' conversations 1 day (server session only), accounts' 30 days, interaction records 90 days |
| Directions | `AssistantHandoffController` | A directions link is re-checked when clicked (same mall, still listed) and 404s otherwise |
