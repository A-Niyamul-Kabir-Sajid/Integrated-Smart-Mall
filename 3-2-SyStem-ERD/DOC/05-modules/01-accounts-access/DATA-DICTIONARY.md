# Accounts and Access — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## APP_USER

One account across all customer, shop, and mall interfaces.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| user_id | uuid | PK | No | — | Generated account identifier. |
| display_name | varchar(120) | — | No | — | Public display name. |
| email | varchar(254) | UK | Yes | — | Normalized email; unique when present. |
| phone | varchar(24) | UK | Yes | — | Normalized phone number; unique when present. |
| password_hash | text | — | No | — | Password hash from the authentication library, never plaintext. |
| status | varchar(20) | — | No | — | active, suspended, or anonymized. |
| email_verified_at | timestamptz | — | Yes | — | Successful email verification time. |
| phone_verified_at | timestamptz | — | Yes | — | Successful phone verification time. |
| created_at | timestamptz | — | No | — | Account creation time. |
| updated_at | timestamptz | — | No | — | Last profile or status update. |

Primary key: `(user_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## ROLE

Role catalogue with an explicit authorization scope.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| role_id | uuid | PK | No | — | Role identifier. |
| code | varchar(50) | UK | No | — | Example customer, platform_admin, mall_admin, shop_owner, shop_staff. |
| scope_type | varchar(12) | — | No | — | platform, mall, or shop. |
| name | varchar(80) | — | No | — | Human-readable role name. |

Primary key: `(role_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## USER_ROLE

Platform-wide account role assignment.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| user_id | uuid | PK, FK | No | APP_USER.user_id | Assigned account. |
| role_id | uuid | PK, FK | No | ROLE.role_id | Assigned platform-scoped role. |
| assigned_at | timestamptz | — | No | — | Assignment time. |

Primary key: `(user_id, role_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## MALL_MEMBERSHIP

A user with one particular role within one mall.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| mall_membership_id | uuid | PK | No | — | Membership identifier. |
| user_id | uuid | FK | No | APP_USER.user_id | Member account. |
| mall_id | uuid | FK | No | MALL.mall_id | Authorized mall. |
| role_id | uuid | FK | No | ROLE.role_id | Mall-scoped role. |
| status | varchar(16) | — | No | — | active or revoked. |
| assigned_at | timestamptz | — | No | — | Assignment time. |

Primary key: `(mall_membership_id)`.

Additional unique keys: `(user_id, mall_id, role_id)`.

Suggested query indexes: `(mall_id, status)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## SHOP_MEMBERSHIP

A user with one particular role within one shop.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| shop_membership_id | uuid | PK | No | — | Membership identifier. |
| user_id | uuid | FK | No | APP_USER.user_id | Member account. |
| shop_id | uuid | FK | No | SHOP.shop_id | Authorized shop. |
| role_id | uuid | FK | No | ROLE.role_id | Shop-scoped role. |
| status | varchar(16) | — | No | — | active or revoked. |
| assigned_at | timestamptz | — | No | — | Assignment time. |

Primary key: `(shop_membership_id)`.

Additional unique keys: `(user_id, shop_id, role_id)`.

Suggested query indexes: `(shop_id, status)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## AUTH_SESSION

Revocable login sessions; not route sessions.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| session_id | uuid | PK | No | — | Authentication session identifier. |
| user_id | uuid | FK | No | APP_USER.user_id | Authenticated account. |
| refresh_token_hash | varchar(128) | UK | No | — | Digest of a high-entropy refresh credential. |
| created_at | timestamptz | — | No | — | Session start. |
| expires_at | timestamptz | — | No | — | Session expiry. |
| revoked_at | timestamptz | — | Yes | — | Explicit revocation time. |

Primary key: `(session_id)`.

Suggested query indexes: `(user_id, expires_at)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.
