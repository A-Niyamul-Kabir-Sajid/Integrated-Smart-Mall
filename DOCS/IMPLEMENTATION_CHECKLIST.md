# Feature handoff checklist

Read [Start here](00_START_HERE.md), then fill this in for the requested slice:

```text
Task and acceptance criteria:
Requirement ID / source links:
Owning app module:
Affected consumers:
Controller / route / view (if needed):
Service / public contract:
Model / existing table owner:
Business rules / dictionary / ERD:
Relevant open decisions and resolution:
Authorization and transaction boundaries:
Pattern and reason (or none):
Tests / evidence required:
```

Check the existing source before adding a class. Keep controllers thin, business workflows in services, repositories selective, and cross-module writes behind the owning interface. Do not implement placeholder features or invent schema from folder names.

After implementation, update affected contracts, focused tests and [progress](PROGRESS.md). Report implemented versus planned behavior separately, including failed checks and integration limitations.
