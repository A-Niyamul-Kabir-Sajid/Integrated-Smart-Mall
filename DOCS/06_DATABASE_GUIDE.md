# Database guide

PostgreSQL is the selected target. The preserved [ERD package](references/erd/README.md) is the single reference for the existing **proposed logical schema**. Its 62 tables do not represent implemented migrations.

Before creating or modifying a model/migration, read:

1. [Open design decisions](references/erd/DOC/03-database/OPEN-DECISIONS.md) and [current conflicts](CONFLICTS.md).
2. [Conventions](references/erd/DOC/03-database/CONVENTIONS.md).
3. [Table ownership](references/erd/DOC/03-database/TABLE-OWNERSHIP.md).
4. [Relationships](references/erd/DOC/03-database/RELATIONSHIPS.md) and [cross-module relationships](references/erd/DOC/03-database/CROSS-MODULE-RELATIONS.md).
5. [Constraint enforcement](references/erd/DOC/03-database/CONSTRAINT-ENFORCEMENT.md) and [data lifecycle](references/erd/DOC/03-database/DATA-LIFECYCLE.md).
6. Affected module business rules, data dictionary and ERD via [the module map](05_MODULE_MAP.md).
7. Existing Laravel migrations, models, factories and tests.

[SCHEMA.json](references/erd/DOC/03-database/SCHEMA.json) is the machine-readable logical design; [the diagram gallery](references/erd/OPEN-DIAGRAMS.html) supplies offline rendered views. Mermaid, Markdown wrappers, SVGs and HTML are complementary representations, not accidental duplicate files.

Nine ERD domains do not imply nine application modules, nor do 13 application folders authorize 13 separate schemas. The scaffold's Mxx identifiers have different meanings from the ERD's Mxx identifiers. Never translate an ID without its source package.

The default Laravel User/auth/session schema must be reconciled with APP_USER/AUTH_SESSION before identity migrations. Do not create parallel account stores. Features missing from the ERD require an explicit design decision; dashboard ownership alone is not table ownership.

The [original validation report](references/erd/DOC/03-database/VALIDATION-REPORT.md) records historical diagram checks, not current application tests. Composite constraints in the enforcement plan must be considered beyond the scalar FK list when ordering future migrations.
