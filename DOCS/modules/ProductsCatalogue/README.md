# ProductsCatalogue

## Responsibility

Shared products, variants, shop listings and availability. Source folder: [app/Modules/ProductsCatalogue](../../../app/Modules/ProductsCatalogue/).

Status: folder architecture only; this page routes to existing requirements, not implemented APIs.

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 02: mall directory product discovery](../../references/functional/DOC/modules/02_mall_directory_product_discovery/GUIDE.md), including its completion checks.
- [Catalogue section 08: shop owner operations](../../references/functional/DOC/modules/08_shop_owner_operations/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 03-products-catalogue: [overview](../../references/erd/DOC/05-modules/03-products-catalogue/README.md), [business rules](../../references/erd/DOC/05-modules/03-products-catalogue/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/03-products-catalogue/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/03-products-catalogue/ERD.md).

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: MallShopDirectory, ShopOwnerOperations, PurchaseVerification, ChatbotRag. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-01, C-06. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.
