# 3 2 SyStem — detailed ER diagram pack

Proposed logical schema v1.0 · Prepared 29 September 2026.

This package covers all **nine proposed modules** from our latest discussion. It contains **62 tables, 399 attributes, 112 field-level FK relationships, and 30 Mermaid diagrams**. Tables marked Optional or Prototype are separated from the core functional design in each data dictionary.

## Open the diagrams

1. Extract the whole ZIP; keep its folders together.
2. Open **OPEN-DIAGRAMS.html** in a desktop browser. It contains the rendered diagrams and works offline. Select a module/focused view, zoom, and drag or scroll to inspect details. No installation or server is required.
3. You can also open any **.svg** directly in a browser or vector editor.
4. To edit a diagram, use its **.mmd** source. Paste that source into [Mermaid Live Editor](https://mermaid.live/) or another Mermaid-capable editor. The **ERD.md** version contains a fenced Mermaid block for Markdown renderers that support Mermaid.
5. SVG and HTML files are previews of this version; changing Mermaid source does not automatically regenerate them. Re-render changed sources in your Mermaid tool and update all affected views.

## Module folders

| Folder | Module | Owned tables |
| --- | --- | --- |
| DOC/05-modules/01-accounts-access/ | Accounts and Access | 6 |
| DOC/05-modules/02-mall-shop-directory/ | Mall and Shop Directory | 7 |
| DOC/05-modules/03-products-catalogue/ | Products and Catalogue | 6 |
| DOC/05-modules/04-maps-navigation/ | Maps and Navigation | 13 |
| DOC/05-modules/05-purchase-verification/ | Purchase Verification | 4 |
| DOC/05-modules/06-reviews-moderation/ | Reviews and Moderation | 5 |
| DOC/05-modules/07-favourites-preferences/ | Favourites and Preferences | 3 |
| DOC/05-modules/08-chatbot-rag/ | Chatbot and RAG | 8 |
| DOC/05-modules/09-analytics-reports/ | Analytics and Reports | 10 |

Each module has README.md, ERD.mmd, ERD.md, ERD.svg, DATA-DICTIONARY.md, and BUSINESS-RULES.md. Larger modules also have focused diagram sources/previews in views/.

## Shared files

- `DOC/03-database/MASTER-ERD.mmd` and `.svg`: full system, keys and relationships only.
- `DOC/03-database/SCHEMA.json`: machine-readable logical definitions used to generate this pack.
- `DOC/03-database/TABLE-OWNERSHIP.md`: exactly one owner for each real table.
- `DOC/03-database/CROSS-MODULE-RELATIONS.md`: foreign keys between modules.
- `DOC/03-database/CONSTRAINT-ENFORCEMENT.md`: constraints that require more than a line on an ERD.
- `DOC/02-architecture/MODULE-CONNECTIONS.md`: end-to-end flows linking modules.
- `DOC/03-database/OPEN-DECISIONS.md`: assumptions and design choices to confirm.
- `DOC/03-database/VALIDATION-REPORT.md`: actual structural/rendering checks.
- `CHECKSUMS.sha256`: archive file integrity checksums (excluding this checksum file itself).

## How to read

**PK** = primary key, **FK** = foreign key, **UK** = single-column unique key. **NN** = required; **NULL** = nullable. Several PK fields form one composite primary key. An `(external Mxx)` box is a key-only reference outside the current view; Mxx identifies its owning module. A focused view may reference another table in the same module. It is not a duplicate table.

Read a focused view first, then its dictionary and business rules. Use the full module diagram for integration and the master as a large reference canvas. The gallery includes all 30 diagrams; the smaller views are usually easier to study.

The previously suggested ShopReview/ProductReview entities are refined into one REVIEW table with a nullable listing target. This keeps shared moderation and verification consistent. Detailed rationale appears in module 06.

## Design status

This is a complete proposed ERD package for the nine-module scope, not production migrations or a report of completed implementation. It does not automatically add unrequested systems such as checkout, payments, delivery, tenancy, or a stock ledger. No final framework/database/AR vendor is assumed. See OPEN-DECISIONS.md before translating the design into SQL migrations.

Mermaid notation reference: https://mermaid.js.org/syntax/entityRelationshipDiagram.html
