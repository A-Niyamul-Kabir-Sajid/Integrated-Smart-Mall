# Decisions

Recorded 2026-10-06. This log distinguishes explicit user selections from documentation-maintenance choices. It does not approve unresolved schema or product decisions.

| ID | Decision | Basis/status |
|---|---|---|
| D-001 | Existing root Laravel application; modular MVC with service layer and selective repositories | Explicit user direction |
| D-002 | Use the 13 exact application folder names in the module map | Latest explicit folder task; supersedes old source-layout naming |
| D-003 | PostgreSQL target; Blade/JavaScript/Alpine.js initially; Three.js/WebXR navigation components later | User-selected architecture goal; no configuration changed here |
| D-004 | Preserve detailed ERDs and rules; no duplicate tables for owner/admin dashboards | Explicit user direction |
| D-005 | Use DOCS as the active entry point; link to detailed sources, archive superseded templates | Documentation maintenance under current cleanup request |
| D-006 | Preserve the 12 catalogue sections as product groupings and nine ERD domains as proposed data owners | Mapping clarification; does not rewrite their contents or approve all scope |
| D-007 | Remove only the duplicate UTF-16 directory export after decoded equality check | Cleanup evidence in maintenance report; UTF-8 copy retained |
| D-008 | Identity: Laravel's `users` table is the physical APP_USER; Laravel database sessions replace AUTH_SESSION (see mapping below) | Approved by the user 2026-11-03; resolves C-05; affects AccountsAccess and every module referencing users |
| D-009 | SharedPlatform owns two tables outside the ERD: `notifications` (in-app notifications) and `audit_logs` (append-only audit trail); actor/notifiable ids are strings without foreign keys | Approved by the user 2026-11-03; required by the shared-platform guide and Jira ISM-14/ISM-15 |
| D-010 | Account preferences live in FavouritesPreferences (ERD owner of USER_PREFERENCE), not AccountsAccess; two columns `notify_by_email` and `notify_by_sms` extend USER_PREFERENCE for the notification choices Jira ISM-158 requires | Implementation of ISM-24/ISM-158 on 2026-11-03; also covers most of ISM-89 (EPIC-08) |
| D-011 | Shared catalogue with guarded edits: products and variants are shared across shops, each shop adds its own SHOP_PRODUCT listing; a shop may edit shared product details only while it is the sole lister, otherwise the mall administrator curates them | Approved by the user 2026-11-03; resolves C-06; affects ProductsCatalogue and ShopOwnerOperations |

The [original guide decisions](archive/project-guide/docs/DECISIONS.md) and [scaffold decisions](references/functional/DOC/project/DECISIONS.md) remain historical evidence. Their previous architecture/source-layout claims do not supersede D-001/D-002.

Record material new decisions with date, rationale, status, source of approval and affected modules. Open questions stay in [CONFLICTS.md](CONFLICTS.md).

## D-008 identity mapping

| ERD (APP_USER) | Physical `users` column | Notes |
|---|---|---|
| user_id uuid PK | `user_id` uuid primary key | Laravel's default bigint `id` is replaced |
| display_name | `display_name` | Replaces Laravel's `name` |
| email (UK, nullable) | `email` unique, nullable | Stored lower-case and trimmed |
| phone (UK, nullable) | `phone` unique, nullable | Stored in E.164 via `PhoneNumberNormalizer` |
| password_hash | `password_hash` | `User::$authPasswordName` points Laravel's guard at it; never plaintext |
| status | `status` enum active/suspended/anonymized | Only active accounts may sign in |
| email_verified_at, phone_verified_at | same | |
| created_at, updated_at | same | |
| — | `remember_token` | Framework addition for "keep me signed in" |

- AUTH_SESSION is not created. The browser application uses Laravel's server-side `sessions` table (its `user_id` holds the UUID); revoking a session deletes its row. Refresh tokens are unnecessary for a session-based web app; revisit if a mobile or API client is added.
- ROLE, USER_ROLE, MALL_MEMBERSHIP and SHOP_MEMBERSHIP follow the ERD unchanged. Role codes: `customer` and `platform_admin` (platform scope), `mall_admin` (mall scope), `shop_owner` and `shop_staff` (shop scope).
- Business rules kept: at least one of email or phone for an active account; anonymised accounts keep their id, lose personal fields and cannot sign in; guests create no account.
- `password_reset_tokens` (framework table) supports reset by email; phone-only accounts need an SMS reset flow later.
