# Accounts and Access: complete module ER diagram

Users, scoped roles, memberships, and login sessions.

External entities display their primary keys only and are owned by the indicated module. All attributes of this module are shown. Relationship labels name the foreign-key field on the child.

```mermaid
erDiagram
    direction TB
    %% Accounts and Access
    %% NN = required; NULL = nullable. Composite unique/check rules are in the module documents.
    APP_USER {
        uuid user_id PK "NN"
        varchar(120) display_name "NN"
        varchar(254) email UK "NULL"
        varchar(24) phone UK "NULL"
        text password_hash "NN"
        varchar(20) status "NN"
        timestamptz email_verified_at "NULL"
        timestamptz phone_verified_at "NULL"
        timestamptz created_at "NN"
        timestamptz updated_at "NN"
    }
    ROLE {
        uuid role_id PK "NN"
        varchar(50) code UK "NN"
        varchar(12) scope_type "NN"
        varchar(80) name "NN"
    }
    USER_ROLE {
        uuid user_id PK, FK "NN"
        uuid role_id PK, FK "NN"
        timestamptz assigned_at "NN"
    }
    MALL_MEMBERSHIP {
        uuid mall_membership_id PK "NN"
        uuid user_id FK "NN"
        uuid mall_id FK "NN"
        uuid role_id FK "NN"
        varchar(16) status "NN"
        timestamptz assigned_at "NN"
    }
    SHOP_MEMBERSHIP {
        uuid shop_membership_id PK "NN"
        uuid user_id FK "NN"
        uuid shop_id FK "NN"
        uuid role_id FK "NN"
        varchar(16) status "NN"
        timestamptz assigned_at "NN"
    }
    AUTH_SESSION {
        uuid session_id PK "NN"
        uuid user_id FK "NN"
        varchar(128) refresh_token_hash UK "NN"
        timestamptz created_at "NN"
        timestamptz expires_at "NN"
        timestamptz revoked_at "NULL"
    }
    MALL["MALL (external M02)"] {
        uuid mall_id PK "NN"
    }
    SHOP["SHOP (external M02)"] {
        uuid shop_id PK "NN"
    }
    APP_USER ||--o{ USER_ROLE : "user_id"
    ROLE ||--o{ USER_ROLE : "role_id"
    APP_USER ||..o{ MALL_MEMBERSHIP : "user_id"
    MALL ||..o{ MALL_MEMBERSHIP : "mall_id"
    ROLE ||..o{ MALL_MEMBERSHIP : "role_id"
    APP_USER ||..o{ SHOP_MEMBERSHIP : "user_id"
    SHOP ||..o{ SHOP_MEMBERSHIP : "shop_id"
    ROLE ||..o{ SHOP_MEMBERSHIP : "role_id"
    APP_USER ||..o{ AUTH_SESSION : "user_id"
```

See [data dictionary](DATA-DICTIONARY.md) and [business rules](BUSINESS-RULES.md) for checks that a diagram cannot express.
