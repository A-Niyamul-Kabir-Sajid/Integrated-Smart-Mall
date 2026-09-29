# M10 — Mall-owner administration

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Shop approval and management:** Approve tenants and maintain their platform records.
- **Mall structure management:** Manage buildings, floors, units, and categories.
- **Map editor:** Update shop locations, walkable paths, and facilities.
- **Navigation graph management:** Maintain nodes, edges, distances, and accessibility attributes.
- **QR checkpoint management:** Generate, assign, replace, and disable checkpoint codes.
- **Closures and restrictions:** Record unavailable corridors, shops, or facilities.
- **Role and permission management:** Control access for administrators and operational staff.
- **Content moderation:** Review reported products, promotions, and reviews.
- **Announcements:** Publish mall-wide notices and events.
- **Complaint management:** Assign cases, track progress, and record resolutions.
- **Maintenance management:** Create work orders, assign staff, and track completion.
- **Tenant administration:** Optionally manage leases, rent, service charges, and renewal dates.
- **Advertising management:** Manage promoted placements and clearly label sponsored content.
- **Audit history:** Record important administrative changes.

## Ownership and write boundaries
Own tenant approvals, mall/building/unit/facility records, administrative policies, notices, operational cases, maintenance and optional lease/dues/ad-placement records. Administer M03 maps, M05 moderation and M12 roles through their public services.

## Internal components
- `tenant_structure/`
- `map_graph_checkpoint_admin/`
- `permissions_moderation/`
- `announcements/`
- `complaints_maintenance/`
- `leases_dues/`
- `advertising/`

These are subcomponents within M10, not additional top-level modules. See [source folder](../../../modules/10_mall_owner_administration/README.md).

## Proposed contract
AdminCommand {actorId, mallId, action, targetId, expectedVersion?}; MapPublish delegates to M03 validation; OperationalCase records assignment, status and resolution history.

## Dependencies
M03, M05, M07, M08, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Only authorized admins approve tenants/publish maps.
- [ ] Graph updates validate.
- [ ] Moderation decisions are recorded.
- [ ] Maintenance cases have explicit state transitions.
- [ ] Financial tenant modules remain disabled until implemented..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.
