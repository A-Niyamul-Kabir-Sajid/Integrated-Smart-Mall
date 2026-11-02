# AccountsAccess

Identity, customer accounts, profiles and scoped access (platform, mall and shop roles).

- Namespace: `App\Modules\AccountsAccess`
- Jira: EPIC-02 Accounts & Access
- Requirements, business rules and ERD: [DOCS/modules/AccountsAccess/README.md](../../../DOCS/modules/AccountsAccess/README.md)
- Physical mapping: [DOCS/DECISIONS.md, D-008](../../../DOCS/DECISIONS.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented: accounts and roles (2.2), registration (2.3), sign-in (2.4), memberships and abilities (2.5). Profile, preferences and account security follow in 2.6 to 2.8. Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Domain

| Entity (ERD) | Table | Meaning |
|---|---|---|
| APP_USER | `users` | One account for every interface: customer, shop staff, mall administrator |
| ROLE | `roles` | Role catalogue; each role has a scope: platform, mall or shop |
| USER_ROLE | `user_roles` | Platform-scoped roles held by an account |
| MALL_MEMBERSHIP | `mall_memberships` | A mall-scoped role held for one mall |
| SHOP_MEMBERSHIP | `shop_memberships` | A shop-scoped role held for one shop |

Memberships reference MALL and SHOP from MallShopDirectory, so they are created after the directory tables exist.

### Who is who

| Person | Account | Grants |
|---|---|---|
| Visitor | none (guest) | Browse the directory, search and navigate |
| Customer | `users` row | `customer` platform role, given at registration |
| Shop owner / staff | `users` row | `shop_owner` or `shop_staff` membership per shop; one account may serve several shops |
| Mall administrator | `users` row | `mall_admin` membership per mall |
| Platform operator | `users` row | `platform_admin` platform role |

A role name alone never grants access to every mall or shop: mall and shop permissions always come from an active membership for that specific mall or shop.

### Lifecycle rules

- An active account has at least one of email or phone. Email is stored lower-case; phone in E.164.
- Status `active` may sign in; `suspended` may not; `anonymized` keeps the id for history, clears personal fields and cannot sign in.
- A role's scope type is fixed once assignments exist. USER_ROLE only holds platform roles, MALL_MEMBERSHIP only mall roles, SHOP_MEMBERSHIP only shop roles.
- Memberships are revoked (status `revoked`), not deleted, so the audit trail stays meaningful.

### Provided to other modules

- The authenticated account (`auth()->user()`), its platform roles and its active mall and shop memberships, used by MallOwnerAdmin and ShopOwnerOperations for scoping.
- The abilities `administer-platform`, `administer-mall`, `manage-shop` and `work-in-shop` (named in `SharedPlatform\Enums\Ability`, defined in this module provider); see DOCS/08_SECURITY.md.
- `MembershipService` to grant or revoke mall and shop roles; `RoleAssignmentService` for platform roles.
