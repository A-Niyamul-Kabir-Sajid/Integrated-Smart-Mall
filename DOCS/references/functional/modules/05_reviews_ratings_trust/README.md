# M05 — Reviews, ratings, and trust

[Module guide](../../DOC/modules/05_reviews_ratings_trust/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `normal_reviews/`
- `purchase_verification/`
- `ratings/`
- `responses/`
- `moderation/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
