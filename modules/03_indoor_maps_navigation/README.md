# M03 — Indoor maps and navigation

[Module guide](../../DOC/modules/03_indoor_maps_navigation/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `maps/`
- `checkpoints/`
- `routing/`
- `navigation_ui/`
- `webar/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
