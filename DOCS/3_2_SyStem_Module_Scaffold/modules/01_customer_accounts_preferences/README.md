# M01 — Customer accounts and preferences

[Module guide](../../DOC/modules/01_customer_accounts_preferences/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `accounts/`
- `preferences/`
- `favourites/`
- `visit_history/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
