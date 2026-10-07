# M08 — Shop-owner operations

[Module guide](../../DOC/modules/08_shop_owner_operations/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `onboarding_staff/`
- `catalogue_inventory/`
- `order_fulfilment/`
- `purchase_registration/`
- `promotions/`
- `support/`
- `mall_requests/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
