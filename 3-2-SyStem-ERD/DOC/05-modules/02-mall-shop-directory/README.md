# Module 02 — Mall and Shop Directory

Malls, floors, shop locations, facilities, and opening hours.

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
| MALL | Core | A mall boundary used for directory, map, and permission ownership. |
| FLOOR | Core | Physical level of a mall, independent of any map version. |
| SHOP_CATEGORY | Core | Shop classification such as food, clothing, or electronics. |
| SHOP | Core | One shop business in one mall; locations are represented separately. |
| SHOP_UNIT | Core | A physical unit occupied by a shop; a shop can occupy several floors. |
| SHOP_OPENING_HOUR | Core | Weekly opening intervals, including split opening periods. |
| FACILITY | Core | Public destinations such as toilets, information desks, or prayer rooms. |

## External references

None.

## Design explanation

SHOP represents a mall business; SHOP_UNIT represents its physical spaces. A shop occupying two floors has one SHOP and two SHOP_UNIT rows. This avoids assigning a shop to one floor permanently and makes multiple entrances possible. The same brand in two malls is represented by two SHOP rows in this baseline.

Ownership of business content comes from memberships in module 01. A shop and its unit floor must belong to the same mall. FACILITY is a destination; its graph position belongs in FACILITY_ANCHOR in module 04. Weekly opening hours are local wall-clock times interpreted using MALL.timezone. A complete tenancy, rental, parking, event, or promotion system would be a later module, not an implied feature of this schema.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.
