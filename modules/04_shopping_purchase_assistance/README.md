# M04 — Shopping and purchase assistance

[Module guide](../../DOC/modules/04_shopping_purchase_assistance/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `shopping_lists/`
- `enquiries_messaging/`
- `reservations_orders/`
- `receipts_returns/`
- `coupons_loyalty/`
- `payments/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
