# M12 — Shared platform requirements

[Module guide](../../DOC/modules/12_shared_platform_requirements/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `identity_authorization/`
- `notifications/`
- `audit/`
- `privacy_security/`
- `backup_recovery/`
- `accessibility_localization/`
- `offline_cache/`
- `integrations/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
