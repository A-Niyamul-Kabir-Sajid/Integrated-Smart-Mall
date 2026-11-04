# AccountsAccess

Identity, customer accounts, profiles and scoped access (platform, mall and shop roles).

- Namespace: `App\Modules\AccountsAccess`
- Jira: EPIC-02 Accounts & Access
- Requirements, business rules and ERD: [DOCS/modules/AccountsAccess/README.md](../../../DOCS/modules/AccountsAccess/README.md)
- Physical mapping: [DOCS/DECISIONS.md, D-008](../../../DOCS/DECISIONS.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

EPIC-02 complete: accounts and roles, registration, sign-in, memberships and abilities, profile and account security. Account preferences live in FavouritesPreferences (decision D-010). Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Endpoints

| Method and path | Access | Purpose |
|---|---|---|
| `GET/POST /register` | guest, `throttle:registration` | Create a customer account (email or mobile) |
| `GET/POST /login` | guest, `throttle:login` | Sign in with email or mobile number |
| `POST /logout` | auth | Sign out |
| `GET/POST /forgot-password` | guest, `throttle:password-reset` | Request a reset link by email |
| `GET /reset-password/{token}`, `POST /reset-password` | guest, `throttle:password-reset` | Choose a new password with a valid link |
| `GET /account` | auth | Own account summary |
| `GET/PUT /account/profile` | auth | Edit own name and contacts (current password for contact changes) |
| `GET/PUT /account/password` | auth, `throttle:password-change` | Change own password |

## Services, events and audit actions

| Service | Does | Audit actions |
|---|---|---|
| `RegistrationService` | Customer account with the customer role; dispatches `Registered` | `account.registered`, `access.role_assigned` |
| `RoleAssignmentService` | Platform roles (customer, platform_admin) | `access.role_assigned`, `access.role_revoked` |
| `MembershipService` | Mall and shop roles for one mall or shop | `access.role_assigned`, `access.role_revoked` (mall/shop scope) |
| `ProfileService` | Own profile; changed contacts lose verification | `account.profile_updated` (field names only) |
| `AccountSecurityService` | Password change and reset; ends sessions; `PasswordChanged` notice; dispatches `PasswordReset` | `account.password_changed`, `account.password_reset` |

Demo accounts (one per role) come from `Database\Seeders\DemoAccountsSeeder`.

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
