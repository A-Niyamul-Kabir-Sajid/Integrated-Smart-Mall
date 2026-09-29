# Design assumptions and decisions to confirm

These choices allow a coherent first design. They are proposals, not newly inferred commitments from earlier chats.

| Topic | Baseline in this pack | Change if needed |
| --- | --- | --- |
| Product name | Working title 3 2 SyStem | Rename documentation when a final public name is chosen |
| Module scope | The nine modules in the immediately preceding discussion | Add new modules only after their requirements are specified |
| Database | One relational database; PostgreSQL-like logical type names | Translate physical types/index syntax to the chosen database |
| Deployment scope | Multi-mall-capable data model; start with one mall | Keep mall boundaries even in a one-mall demo |
| Shop locations | One shop in one mall, multiple physical units/floors | Add brand/tenant-history entities if chain management or leases become requirements |
| Catalogue | Shared products/variants with shop-specific listings | Use shop-owned products if shared catalogue curation is unwanted |
| Authentication | Email/phone account with password; guests browse without accounts | Adapt to chosen framework or social/OTP auth; challenge/MFA tables are not claimed here |
| Reviews | One editable review per author/target; listing-specific product reviews | A per-purchase review policy changes uniqueness and review display rules |
| Verified scope | Purchase verification applies to product-listing reviews | Verifying shop reviews needs a separate, explicit entitlement policy |
| Purchase token | One token/entitlement per purchase line; first valid claimant for an unbound walk-in buyer | Bind every purchase to a customer for stricter identity assurance |
| Checkout | Trusted purchase recording/import only | Add orders, payment, refunds, delivery, and inventory ledger only if requested |
| Map/AR | Versioned custom graph and optional measured AR references | Choose/test browser tracking SDK and mapping pipeline separately |
| RAG | Controlled sources, SQL-backed product selection, optional external vector index | Add a selected vector backend/embedding configuration after evaluation |
| Forecasting | Optional experiment; real and demo data kept distinct | Define metric, minimum history, evaluation method, and interpretation before relying on it |
| Retention | Durations are configurable and undecided | Approve concrete retention values before real deployment |

## Proposed scope tiers

- **Core** means a table belongs to the functional design for its module, not that every Core table must be built on day one.
- **Prototype** means a feature whose implementation needs separate feasibility/evaluation work, especially AR, RAG, and forecasting.
- **Optional** means the feature can be omitted while its module's main workflow still works, for example persistent route history, inventory snapshots, or vector indexing.

Tables, diagrams, and SQL alone cannot demonstrate continuous positioning, purchase authenticity beyond the trusted issuer, model accuracy, or real commercial performance. Record evidence from implementation tests for those claims.
