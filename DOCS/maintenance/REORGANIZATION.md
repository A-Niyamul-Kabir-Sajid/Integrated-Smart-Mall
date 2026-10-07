# Documentation audit and reorganization

## Scope

Inventoried 241 original files across 366 subdirectories, including empty source scaffolding. Read project guidance, functional module requirements/contracts/checks, ERD business rules and database guidance; indexed dictionaries and all Markdown headings. Inspected the machine-readable schema and packaging/import workflow. Diagram source/render files were retained rather than regenerated; this audit is not a new visual or business validation of the ERD.

[source-inventory.json](source-inventory.json) records every original file's path, size, SHA-256, Markdown headings, current path and relocation/deduplication action, plus the original directory inventory.

## Physical organization

| Previous location under DOCS | Current location | Action |
|---|---|---|
| 3-2-SyStem-ERD/ | [references/erd/](../references/erd/README.md) | Whole package moved; all file bytes and internal layout preserved |
| 3_2_SyStem_Module_Scaffold/ | [references/functional/](../references/functional/README.md) | Whole package moved; requirements, business rules, source scaffolding and internal links preserved |
| 3_2_SyStem_Project_Guide/ | [archive/project-guide/](../archive/project-guide/README.md) | Superseded package preserved, including placeholder module pages and import script |
| output.txt | [retained UTF-8 inventory](../archive/project-guide/legacy-inventory/CURRENT_DIRECTORY_INVENTORY.txt) | Removed redundant UTF-16 copy only after exact decoded-content equality check |

Active top-level guides were written as concise indexes and current conventions. They link directly to the preserved detailed sources. Thirteen module entry points map the current application names to functional and ERD references. No business-rule or ERD text was merged or rewritten.

## Duplication findings

- No nonempty original files were byte-identical. Ten empty `.gitkeep` files share a hash but represent distinct scaffold locations; they remain in the archived guide.
- The two historical directory inventories contain identical text in UTF-16 and UTF-8. One UTF-8 copy is retained.
- The guide's 60 module files repeat a five-file placeholder template across 12 shorthand modules. These are archived, not promoted as authoritative requirements. Thirteen active README indexes replace their role without copying rule definitions.
- The feature catalogue repeats scope bullets in detailed functional guides. Those guides add ownership, contracts and completion checks; they are not safely removable duplicates. Original text remains intact.
- ERD Markdown, Mermaid, SVG and the embedded HTML gallery are intentional representations of the same design. All are retained, including their checksums/manifest.
- Historical source trees and empty frontend/backend folders are reference scaffolding, not executable app modules. They remain inside their source package to preserve provenance and relative links.

## Problems found

- Conflicting 12-section, 12-shorthand and 13-application-module layouts. The active map follows the user's exact 13 names; catalogue and ERD IDs remain separate namespaces.
- ParkingFacilities was folded into platform in the old guide. It now has its own module entry point.
- The old PRD and per-module requirements/rules/test maps are placeholders; no complete approved acceptance matrix exists.
- Historical progress/context says Laravel is not selected/installed. That is stale relative to the root application and user instructions.
- Functional ownership and ERD ownership differ for credentials, catalogue, purchases/tokens and administration. Unresolved feature implications are recorded in [CONFLICTS.md](../CONFLICTS.md).
- The ERD covers purchase evidence, not the complete orders/payments/reservations scope in the catalogue. Verified shop reviews are also outside its baseline.
- The old merge script advertises safe copying but includes unconditional Force copies and can recreate multiple sources of truth. It was not executed and is archived.
- The functional package README has a trailing mixed-encoding fragment containing 25 NUL bytes. It was preserved verbatim for integrity; use the active entry point instead. The UTF-16 inventory's NUL bytes were normal encoding, not corruption.
- Root AGENTS.md and CLAUDE.md previously routed only to Boost bootstrap instructions. A short shared documentation pointer was added; existing bootstrap text was preserved.

## Verification and limits

Run `python DOCS/maintenance/validate_docs.py`. See [VALIDATION.md](VALIDATION.md) for the recorded result. The checker validates file-target links (not heading anchors or browser rendering), all 13 module guides, and original file integrity using the relocation manifest. Archived historical inline path examples remain historical and are not represented as current links.

No application feature, dependency, route, migration, database configuration or ERD relationship was changed. Application tests were not run for documentation-only edits. Future approved reference changes should explicitly update the integrity baseline; an unexpected mismatch must not be ignored.
