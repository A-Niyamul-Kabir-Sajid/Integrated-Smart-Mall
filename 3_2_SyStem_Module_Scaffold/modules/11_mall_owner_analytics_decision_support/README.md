# M11 — Mall-owner analytics and decision support

[Module guide](../../DOC/modules/11_mall_owner_analytics_decision_support/GUIDE.md) · [Connections](../../DOC/MODULE_CONNECTIONS.md)

This folder is one functional module. Its internal components are:
- `overview_comparisons/`
- `demand_analysis/`
- `review_analysis/`
- `monthly_reports/`
- `performance_forecasting/`
- `admin_assistant/`
- `exports/`

Each component reserves frontend/, backend/, and tests/ subfolders. Use only needed layers; do not duplicate logic on both sides. No executable code, dependency manifest or framework configuration is included. These modules are organizational boundaries, not separate deployable services or installable packages.
