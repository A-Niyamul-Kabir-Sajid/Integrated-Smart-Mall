# SharedPlatform

Cross-cutting platform services: localization, notifications, audit, security conventions and integrations.

- Namespace: `App\Modules\SharedPlatform`
- Jira: EPIC-01 Shared Platform Foundation
- Requirements, business rules and ERD: [DOCS/modules/SharedPlatform/README.md](../../../DOCS/modules/SharedPlatform/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented: security defaults and rate limiters (provider), notification foundation, audit trail. Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Notifications

### Types

Each type is a case of `Enums\NotificationType`; its value is stored in `notifications.type` and keys user channel preferences.

| Type | Notification | Default channels | Owner of the trigger |
|---|---|---|---|
| `system_notice` | `Notifications\SystemNotice` | database | Any module (announcements, changed opening hours) |
| `account_security` | added with password changes (EPIC-02) | database, mail | AccountsAccess |

Planned types, added with their stories: shop application decisions, review replies and moderation outcomes, purchase verification credentials, complaint and maintenance updates.

### Channels

| Channel | Delivery | Notes |
|---|---|---|
| `database` | In-app list (`notifications` table) | Always available; the notification list UI arrives with account pages |
| `mail` | Laravel mail (log driver locally) | Template from `toMail()` using `MailMessage` |
| `sms` | `Notifications\Channels\SmsChannel` → `Contracts\SmsGateway` | Notifiable supplies its number through `routeNotificationForSms()`; skipped when absent |

### Templates

A notification implements `toArray()` for the in-app payload (`title`, `body`, `action_url`), `toMail()` for email and `toSms()` for text. User-facing strings go through `__()` so they can be translated.

### Preferences

`Contracts\NotificationPreferences::channelsFor($notifiable, $type)` decides the channels. `Services\DefaultNotificationPreferences` applies the defaults above; the account preferences story binds an implementation that reads each user's stored settings.

### Adapter boundary

External providers sit behind contracts bound in `Providers\SharedPlatformServiceProvider`. `Adapters\LogSmsGateway` is the default `SmsGateway`: it logs the message with the phone number masked. Integrating a real SMS provider means adding an adapter class and changing that one binding.

## Logging versus audit

| | Diagnostic log | Business audit |
|---|---|---|
| Purpose | Debugging and operations: errors, slow queries, integration failures | Accountability: who changed what, when, in which mall or shop |
| Where | Laravel log channels (`storage/logs`) | `audit_logs` table through `Services\AuditLogger` |
| Lifetime | Rotated; may be deleted | Append-only; the model refuses updates and deletes |
| Correlation | Every line carries `request_id` (`App\Core\Middleware\AssignRequestId`) | Each record stores the same `request_id` |

Never write passwords, tokens or credentials to either. `AuditLogger` redacts metadata keys that look like secrets; diagnostic log calls must not include them in the first place.

## Auditable actions

Record an audit entry from the owning module's service, after the change succeeds, with `AuditLogger::record($action, $subject, $metadata, $mallId, $shopId)`. Actions are dotted codes `<area>.<event>`.

| Action | Recorded by | Metadata |
|---|---|---|
| `account.registered`, `account.password_changed`, `account.password_reset` | AccountsAccess | channel; never the password |
| `access.role_assigned`, `access.role_revoked` | AccountsAccess | role code, scope (mall/shop id) |
| `shop.status_changed` | MallShopDirectory | from, to, reason |
| `listing.price_changed`, `listing.archived` | ProductsCatalogue | from, to |
| `review.moderated` | ReviewsModeration | decision, reason |
| `purchase.registered`, `credential.redeemed` | PurchaseVerification | purchase id; never the raw credential |

Each record holds: `occurred_at`, `action`, `actor_id` (null for system actions), `subject_type` (table) and `subject_id`, `mall_id`/`shop_id` scope, `request_id`, `ip_address`, `user_agent` and `metadata`.
