# M09 — Shop-owner analytics

[Module guide](../../DOC/modules/09_shop_owner_analytics/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `sales/`
- `products_inventory/`
- `discovery/`
- `feedback/`
- `campaigns/`
- `sales_forecasting/`
- `exports/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
