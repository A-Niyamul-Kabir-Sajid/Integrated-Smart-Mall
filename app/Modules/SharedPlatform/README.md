# SharedPlatform

Cross-cutting platform services: localization, notifications, audit, security conventions and integrations.

- Namespace: `App\Modules\SharedPlatform`
- Jira: EPIC-01 Shared Platform Foundation
- Requirements, business rules and ERD: [DOCS/modules/SharedPlatform/README.md](../../../DOCS/modules/SharedPlatform/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented: security defaults and rate limiters (provider), notification foundation. Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

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
