# Security guidance

Read [detailed security and verification guidance](references/functional/DOC/quality/SECURITY_VERIFICATION.md) and the relevant [database business rules](06_DATABASE_GUIDE.md).

Use Laravel authentication, policies/gates, Form Requests, CSRF protection, rate limits and scoped middleware. Enforce shop/mall permissions server-side; browser-supplied roles are not authority. Generic reusable middleware belongs in `app/Core/Middleware/`; domain checks stay with the owning module.

Purchase credentials require trusted purchase evidence, unpredictable or securely signed secrets, expiry/revocation rules, and concurrency-safe redemption. Keep tokens and provider credentials out of logs. Customer RAG must not retrieve private administrative data.

Document consent and retention before collecting real history/camera data. Authorize private report downloads; label synthetic data and simulated integrations. Treat emergency information as approved content, not certified evacuation guidance.

Network firewall/WAF belongs to deployment infrastructure. No Firewall design-pattern folder is needed.
