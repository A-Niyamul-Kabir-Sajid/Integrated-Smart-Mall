# Shared schema and Mermaid conventions

This is a proposed logical relational design for the nine modules agreed in this conversation. It is not a reverse-engineered database or a statement that all features have been implemented. A single shared database is the baseline; module folders represent ownership, not separate database servers.

## Diagram notation

| Notation | Meaning |
| --- | --- |
| PK | Primary key; multiple PK attributes form one composite primary key |
| FK | Foreign key to the referenced table/key |
| UK | Single-column unique key; composite uniqueness is listed in the data dictionary |
| NN | Required / NOT NULL |
| NULL | Optional / nullable |
| `PARENT ||..o{ CHILD` | Each child has exactly one parent; each parent may have zero or many children |
| `PARENT |o..o{ CHILD` | A child may have zero or one parent; a parent may have zero or many children |
| `PARENT ||--o| CHILD` | Each child belongs to exactly one parent; a parent has at most one such child |
| Solid relationship | Identifying in this pack: FK participates in the child's primary key |
| Dashed relationship | Non-identifying: the child has a primary key independent of this FK |
| `(external Mxx)` | Key-only reference outside this view; Mxx is its owner, never a second real table |

A focused view may reference another table from the same module; it is still marked external to that focused view. All relationship labels name the FK attribute on the child. A diagram may have several lines between two tables, for example from_node_id and to_node_id; these are different relationships. Draft parents generally allow zero children. Business rules impose stronger conditions when publishing/completing them.

## Logical to physical types

| Logical type | Proposed mapping and use |
| --- | --- |
| uuid | Native UUID where available; alternatively a consistent UUID representation or generated bigint strategy chosen project-wide |
| varchar(n), char(n), text | Bounded codes/names or unbounded descriptive text; use Unicode |
| timestamptz | UTC-aware instant; render in the user's/mall's timezone |
| time | Local weekly shop opening time interpreted with MALL.timezone |
| decimal for money | Fixed decimal, for example NUMERIC(12,2); choose scale for supported currencies |
| decimal for coordinates/length | Fixed numeric, for example NUMERIC(12,3), after surveying required range/precision |
| decimal for scores/forecasts | Precision appropriate to the metric; never substitute currency values without units |
| integer, smallint, bigint | Integer fields with explicit bounds/checks |
| boolean | True or false; NN means no unknown third state |
| json | Small structured metadata; use actual columns for searchable business keys and authorization rules |

The Mermaid source avoids newer optional-type and ER-subgraph syntax for portability. Scalar decimal has no precision inside the diagram because Mermaid type syntax does not represent comma-separated numeric precision consistently; physical precision is selected in migrations.

## Key and lifecycle rules

- IDs are immutable. Never recycle an ID after deletion or retirement.
- Nullable UK values mean multiple missing values are permitted; select the proper filtered/partial-index equivalent in the chosen database.
- Normalize email/phone before uniqueness checks; specify case/collation behavior.
- Enumerated status values in the data dictionary need CHECK constraints or reference enums. They are not arbitrary strings.
- Use named indexes/constraints in migrations. The diagrams describe semantics and do not execute constraints.
- Publish map datasets atomically and do not mix versions in a route.
- Archive referenced shops/listings/maps and retain purchase/review history. Deletion policy is in DATA-LIFECYCLE.md.
- Never store raw passwords, token secrets, camera frames, or precise continuous visitor trails merely because a table could hold them.
- A captured snapshot has a defined purpose: purchase labels preserve sale history, document revisions preserve citations, and metric snapshots preserve reports. These are intentional historical copies.

Official notation reference: https://mermaid.js.org/syntax/entityRelationshipDiagram.html
