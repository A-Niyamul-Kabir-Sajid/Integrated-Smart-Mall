# Module 07 — Favourites and Preferences

Saved shops, saved listings, and user interface/navigation preferences.

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
| USER_PREFERENCE | Core | Optional one-to-one user settings record. |
| FAVOURITE_SHOP | Core | A shop saved by a user. |
| FAVOURITE_PRODUCT | Core | A shop listing saved by a user, preserving seller context. |

## External references

| Referenced table | Owner |
| --- | --- |
| APP_USER | Accounts and Access |
| MALL | Mall and Shop Directory |
| SHOP | Mall and Shop Directory |
| SHOP_PRODUCT | Products and Catalogue |

## Design explanation

Favourite lists use composite keys to prevent duplicate saves. FAVOURITE_PRODUCT saves a shop-specific listing, not an abstract product, so price and navigation destination remain meaningful. Archived listings may appear as unavailable until the user removes them.

USER_PREFERENCE is optional: missing data means application defaults. Guest browsing and local preferences do not require a server account. ar_preferred is a UI preference, not evidence of camera permission or device support. Personalization history is opt-in; a saved preference must not override current permission checks.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.
