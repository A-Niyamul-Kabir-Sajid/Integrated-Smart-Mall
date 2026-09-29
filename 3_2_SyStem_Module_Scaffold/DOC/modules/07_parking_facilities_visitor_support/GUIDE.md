# M07 — Parking, facilities, and visitor support

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Parking information:** Show parking entrances, zones, charges, and operating hours.
- **Saved parking location:** Record a parking zone or scan a parking checkpoint.
- **Return-to-car navigation:** Route customers back to their saved parking location.
- **Parking availability:** Display occupancy if a reliable source supplies it.
- **Event calendar:** Show exhibitions, sales events, and entertainment.
- **Event registration:** Support booking where required.
- **Lost and found:** Submit reports and track claims.
- **Help requests:** Contact the information desk or request assistance.
- **Facility complaints:** Report broken elevators, cleanliness issues, or other problems.
- **Emergency information:** Display approved contacts, exits, and safety instructions.

## Ownership and write boundaries
Own saved parking locations, public event/visitor-support interfaces, lost-found cases and visitor complaint intake. M10 owns operational complaint assignments/work orders and approved emergency content; keep one case ID across intake and resolution.

## Internal components
- `parking/`
- `events/`
- `lost_found/`
- `help_complaints/`
- `emergency_information/`

These are subcomponents within M07, not additional top-level modules. See [source folder](../../../modules/07_parking_facilities_visitor_support/README.md).

## Proposed contract
VisitorCase {id, type, mallId, submittedBy?, status, operationalCaseId?}; SavedParking {userId, zoneId, checkpointId?}; availability requires source and updatedAt.

## Dependencies
M01, M03, M10, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Saved parking routes to a valid checkpoint.
- [ ] Occupancy has source/time or unavailable state.
- [ ] Visitor claims are access-controlled.
- [ ] Cases keep the same status across intake/admin.
- [ ] Emergency content is approved, not dynamically invented..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.
