# 08 — Security Architecture

Security is cross-cutting, not a GoF pattern.

Use Laravel authentication, RBAC/policies, request validation, CSRF protection, rate limiting, secure session/cookie configuration, output escaping, audit logs, least-privilege database credentials, secret management, security headers, backups, and production network firewall/WAF controls where deployed.

Sensitive flows needing explicit threat review include purchase verification tokens, review eligibility, shop/mall administrative actions, external AI/API credentials, and map/graph administration.
