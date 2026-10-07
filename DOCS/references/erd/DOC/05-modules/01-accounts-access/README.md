# Module 01 — Accounts and Access

Users, scoped roles, memberships, and login sessions.

Status: proposed logical design v1.0; not an implemented or supervisor-approved database.

## Files

- [Full Mermaid source](ERD.mmd) and [Markdown rendering](ERD.md).
- [Data dictionary](DATA-DICTIONARY.md): all attributes, keys, nullability, references, and indexes.
- [Business rules](BUSINESS-RULES.md): constraints and lifecycle decisions.
- `ERD.svg`: ready-to-open vector preview.
- `views/`: smaller diagrams for detailed reading where supplied.

## Owned tables

| Table | Scope | Purpose |
| --- | --- | --- |
| APP_USER | Core | One account across all customer, shop, and mall interfaces. |
| ROLE | Core | Role catalogue with an explicit authorization scope. |
| USER_ROLE | Core | Platform-wide account role assignment. |
| MALL_MEMBERSHIP | Core | A user with one particular role within one mall. |
| SHOP_MEMBERSHIP | Core | A user with one particular role within one shop. |
| AUTH_SESSION | Core | Revocable login sessions; not route sessions. |

## External references

| Referenced table | Owner |
| --- | --- |
| MALL | Mall and Shop Directory |
| SHOP | Mall and Shop Directory |

## Design explanation

Roles belong to a scope. USER_ROLE grants platform roles, MALL_MEMBERSHIP grants access to one mall, and SHOP_MEMBERSHIP grants access to one shop. A shop owner may own several shops through separate memberships; a user may also be a customer. The scope check needs a database trigger or service validation because it reads ROLE. Check both role and active membership for every protected operation.

For the first implementation, keep permission-to-role mappings in application code and document them. A dynamic permission editor would need PERMISSION and ROLE_PERMISSION tables, which are not required by this baseline. Use the chosen authentication framework for password reset, email/phone verification challenge storage, MFA, and password hashing. AUTH_SESSION is the logical revocable session model; do not duplicate framework-owned session tables.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.
