# M12 — Shared platform requirements

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Role-based access:** Separate customer, shop staff, mall administrator, and support permissions.
- **Data isolation:** Ensure each shop can access only its authorised business data.
- **Security:** Protect accounts, purchase credentials, payments, and administrative actions.
- **Privacy controls:** Limit collection, define retention periods, and support account deletion.
- **Notifications:** Deliver relevant order, offer, complaint, and maintenance updates.
- **Audit logs:** Track sensitive changes and actions.
- **Backup and recovery:** Protect maps, catalogues, and operational records.
- **Accessible interface:** Support readable text, keyboard use, and assistive technologies.
- **Mobile-friendly browser access:** Fit the browser-first approach you already want.
- **Poor-connectivity handling:** Cache suitable map and directory content and indicate stale information.
- **Integrations:** Connect external POS, inventory, payment, or parking systems where needed.

## Ownership and write boundaries
Own cross-cutting authentication, authorization, notifications, audit, privacy controls, integrations and operational infrastructure. Business workflows stay in M01–M11; this module supplies reusable technical services.

## Internal components
- `identity_authorization/`
- `notifications/`
- `audit/`
- `privacy_security/`
- `backup_recovery/`
- `accessibility_localization/`
- `offline_cache/`
- `integrations/`

These are subcomponents within M12, not additional top-level modules. See [source folder](../../../modules/12_shared_platform_requirements/README.md).

## Proposed contract
ActorContext {userId?, mallRoles, shopMemberships}; AuditEvent {actorId, action, target, timestamp}; Notification {recipientId, type, referenceId}; IntegrationResult includes source, timestamp and failure state.

## Dependencies
Provides shared services to all other modules; business modules remain owners of their rules.

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Cross-shop/cross-mall access fails.
- [ ] Secrets stay server-side.
- [ ] Notification preferences are honored.
- [ ] Backups restore.
- [ ] Offline data is marked stale.
- [ ] Adapter retries are idempotent.
- [ ] Audit logs omit credentials/raw tokens..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.
