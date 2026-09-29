# Accounts and Access — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## APP_USER

- At least one of email or phone is present for an active account.
- Anonymized accounts retain their ID for history; remove personal fields and disable authentication.
- Guest browsing creates no APP_USER row. Email/phone login is the proposed initial authentication model.

## ROLE

- Role scope is immutable after assignments exist.
- A role name alone never grants access to every mall or shop.

## USER_ROLE

- Composite primary key (user_id, role_id). ROLE.scope_type must be platform.

## MALL_MEMBERSHIP

- Unique tuple: `(user_id, mall_id, role_id)`.
- ROLE.scope_type must be mall.

## SHOP_MEMBERSHIP

- Unique tuple: `(user_id, shop_id, role_id)`.
- ROLE.scope_type must be shop.

## AUTH_SESSION

- expires_at > created_at. Never save a raw refresh token.
