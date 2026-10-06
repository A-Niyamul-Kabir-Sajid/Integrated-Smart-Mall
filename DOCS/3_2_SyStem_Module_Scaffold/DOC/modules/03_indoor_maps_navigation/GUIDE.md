# M03 — Indoor maps and navigation

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
These features form the core of your existing project.

- **Custom floor maps:** Display the mall’s layout, shops, corridors, and facilities.
- **QR starting location:** Scan a pillar or checkpoint QR code to identify the starting position.
- **Destination selection:** Choose a shop, product’s shop, or facility as the destination.
- **Shortest-path routing:** Calculate routes using A* or Dijkstra.
- **Multi-floor routing:** Connect floors through stairs, escalators, and elevators.
- **Accessible routing:** Offer routes that avoid stairs where suitable paths exist.
- **Map-based guidance:** Display the route on a 2D or 3D map.
- **WebAR guidance:** Show directional guidance through the camera view, subject to the positioning and tracking approach you validate.
- **AR visibility toggle:** Switch between navigation and the shopping dashboard.
- **Checkpoint re-positioning:** Scan another QR code to update the known location.
- **Route recalculation:** Recalculate after a location update, destination change, or corridor closure.
- **Multi-stop journeys:** Plan visits to several shops.
- **Distance and estimated walking time:** Show route length and approximate travel time.
- **Temporary restrictions:** Avoid closed corridors or unavailable connectors.
- **Shareable destinations:** Share a shop or meeting-point link with another visitor.

**Important design distinction:** A QR scan establishes a known checkpoint. Continuously updating the customer’s position while walking requires a separate tracking solution.

## Ownership and write boundaries
Own versioned maps, graphs, checkpoints, routing, navigation state and AR integration. M10 provides administrative screens that call these domain services; do not create a second graph in M10.

## Internal components
- `maps/`
- `checkpoints/`
- `routing/`
- `navigation_ui/`
- `webar/`

These are subcomponents within M03, not additional top-level modules. See [source folder](../../../modules/03_indoor_maps_navigation/README.md).

## Proposed contract
RouteRequest {mapVersion, startNodeId, destinationNodeIds, preferences}; RouteResult {routeId, mapVersion, perFloorSegments, transitions, totalDistanceM, estimatedDuration?}. PoseStatus carries source, timestamp and tracking quality separately.

## Dependencies
M01, M02, M10, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Known graphs return correct paths.
- [ ] Unreachable destinations are handled.
- [ ] Accessible paths enforce constraints.
- [ ] Map versions stay coherent.
- [ ] Qr does not imply continuous pose.
- [ ] Webar has real-device alignment/tracking-loss evidence..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.
