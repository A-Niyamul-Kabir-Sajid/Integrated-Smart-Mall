# Decisions

Recorded 2026-10-06. This log distinguishes explicit user selections from documentation-maintenance choices. It does not approve unresolved schema or product decisions.

| ID | Decision | Basis/status |
|---|---|---|
| D-001 | Existing root Laravel application; modular MVC with service layer and selective repositories | Explicit user direction |
| D-002 | Use the 13 exact application folder names in the module map | Latest explicit folder task; supersedes old source-layout naming |
| D-003 | PostgreSQL target; Blade/JavaScript/Alpine.js initially; Three.js/WebXR navigation components later | User-selected architecture goal; no configuration changed here |
| D-004 | Preserve detailed ERDs and rules; no duplicate tables for owner/admin dashboards | Explicit user direction |
| D-005 | Use DOCS as the active entry point; link to detailed sources, archive superseded templates | Documentation maintenance under current cleanup request |
| D-006 | Preserve the 12 catalogue sections as product groupings and nine ERD domains as proposed data owners | Mapping clarification; does not rewrite their contents or approve all scope |
| D-007 | Remove only the duplicate UTF-16 directory export after decoded equality check | Cleanup evidence in maintenance report; UTF-8 copy retained |

The [original guide decisions](archive/project-guide/docs/DECISIONS.md) and [scaffold decisions](references/functional/DOC/project/DECISIONS.md) remain historical evidence. Their previous architecture/source-layout claims do not supersede D-001/D-002.

Record material new decisions with date, rationale, status, source of approval and affected modules. Open questions stay in [CONFLICTS.md](CONFLICTS.md).
