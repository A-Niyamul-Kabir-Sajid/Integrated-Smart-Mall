# M07 — Parking, facilities, and visitor support

[Module guide](../../DOC/modules/07_parking_facilities_visitor_support/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `parking/`
- `events/`
- `lost_found/`
- `help_complaints/`
- `emergency_information/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
