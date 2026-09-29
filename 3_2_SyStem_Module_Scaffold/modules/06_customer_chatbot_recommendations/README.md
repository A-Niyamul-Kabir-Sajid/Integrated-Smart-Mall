# M06 — Customer chatbot and recommendations

[Module guide](../../DOC/modules/06_customer_chatbot_recommendations/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `retrieval/`
- `recommendations/`
- `faq/`
- `navigation_handoff/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
