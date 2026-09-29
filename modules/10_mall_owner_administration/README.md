# M10 — Mall-owner administration

[Module guide](../../DOC/modules/10_mall_owner_administration/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `tenant_structure/`
- `map_graph_checkpoint_admin/`
- `permissions_moderation/`
- `announcements/`
- `complaints_maintenance/`
- `leases_dues/`
- `advertising/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
