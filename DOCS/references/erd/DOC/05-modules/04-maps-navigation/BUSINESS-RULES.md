# Maps and Navigation — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## MAP_VERSION

- Unique tuple: `(mall_id, version_no)`.
- At most one published version per mall, using a conditional unique index.
- All node coordinates use metres: x and y horizontal, z up. The mall origin and axis alignment are documented before mapping.
- Published versions are immutable. Publish a replacement atomically; keep retired data for active sessions/history.

## FLOOR_MAP

- Unique tuple: `(map_version_id, floor_id)`.
- metres_per_pixel > 0. FLOOR.mall_id must equal MAP_VERSION.mall_id.
- This affine transform assumes uniform scale and no skew; rectification is a map preparation task.

## NAVIGATION_NODE

- Unique tuple: `(map_version_id, node_code)`.
- Unique tuple: `(node_id, map_version_id)`.
- Unique tuple: `(node_id, map_version_id, floor_id)`.
- Require a FLOOR_MAP for this (map_version_id, floor_id) using a composite FK.

## VERTICAL_CONNECTOR

- Apply declared keys, foreign keys, nullability, and field meanings.

## CONNECTOR_STOP

- Unique tuple: `(connector_id, map_version_id, floor_id)`.
- Node version and floor must match the stop; connector, floor, and map must belong to the same mall.

## NAVIGATION_EDGE

- Unique tuple: `(map_version_id, from_node_id, to_node_id)`.
- Unique tuple: `(edge_id, map_version_id)`.
- from_node_id != to_node_id; length_m > 0; expected_seconds > 0.
- Both endpoint nodes belong to this map version; enforce composite endpoint FKs.
- For a cross-floor edge, both endpoints must be stops of connector_id in this version.
- Choose distance or time as one consistent route cost. An A* heuristic must be a lower bound in that same unit; use h=0 if no defensible bound exists.
- Published geometry/costs are immutable. Temporary status changes are operational exceptions and trigger rerouting.

## QR_CHECKPOINT

- Unique tuple: `(mall_id, checkpoint_code)`.
- Resolve the QR to an anchor in the currently published map. Never embed a version-specific node ID as the only permanent QR reference.

## CHECKPOINT_ANCHOR

- Composite primary key (checkpoint_id, map_version_id). Node version and checkpoint mall must match.
- Scanning a URL supplies checkpoint identity. Camera pose recovery requires a supported visual anchor/alignment workflow; this row alone does not provide continuous tracking.

## SHOP_ENTRANCE

- Unique tuple: `(shop_unit_id, map_version_id, node_id)`.
- At most one primary entrance per (shop_unit_id, map_version_id), using a conditional unique index.
- Unit floor equals node floor; node version and mall must match.

## FACILITY_ANCHOR

- Composite primary key (facility_id, map_version_id); node floor/version/mall must match the facility.

## AR_REFERENCE_ANCHOR

- Node belongs to map_version_id. Euler convention is intrinsic Z-Y-X; the runtime must convert from its own coordinate frame explicitly.
- Physical width is positive for image/fiducial targets; provider calibration is separately required for a VPS reference.
- An AR SDK/browser/device integration must be tested independently. LiDAR assets do not automatically provide browser localization.

## ROUTE_SESSION

- Both nodes belong to the pinned map version.
- Route computation may be stateless; do not persist ROUTE_SESSION/ROUTE_STEP unless this optional feature is enabled.

## ROUTE_STEP

- Composite primary key (route_session_id, step_no). Steps are contiguous from zero.
- First/last node match the session endpoints. Each incoming edge connects the preceding node to this node in the pinned version.
