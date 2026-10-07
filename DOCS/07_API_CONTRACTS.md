# API and module contracts

No application API is declared implemented by this guide. The detailed [proposed contracts](references/functional/DOC/integration/CONTRACTS.md), individual feature guides and [integration scenarios](references/functional/DOC/integration/SCENARIOS.md) provide starting requirements.

Their M01-M12 IDs refer to **catalogue sections**. Translate through [the module map](05_MODULE_MAP.md); do not reuse their old data-owner assignments without checking [conflicts](CONFLICTS.md) and [ERD table ownership](references/erd/DOC/03-database/TABLE-OWNERSHIP.md).

For every new service/API contract record:

- Owning application module, callers, command/query/event name, and version.
- Inputs, output shape, canonical identifiers, validation and pagination.
- Authentication, shop/mall scope and authorization at the protected operation.
- Errors, failure behavior, transaction boundaries, concurrency and idempotency.
- Event timing, retries, stale/cache behavior and integration evidence.

Priority flows: directory -> navigation destination; shop operation -> recorded purchase -> token redemption -> review evidence; catalogue/reviews -> customer assistant; domain records -> analytics; admin command -> validated map publication.

Do not infer event execution order from a list of listeners. Required atomic behavior belongs in a defined transaction; independent reactions can use Laravel events/jobs with deliberate after-commit behavior.
