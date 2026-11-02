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
