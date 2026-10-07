# Module 04 — Maps and Navigation

Versioned floor maps, routing graphs, QR anchors, vertical connectors, and AR references.

Status: proposed logical design v1.0; not an implemented or supervisor-approved database.

## Files

- [Full Mermaid source](ERD.mmd) and [Markdown rendering](ERD.md).
- [Data dictionary](DATA-DICTIONARY.md): all attributes, keys, nullability, references, and indexes.
- [Business rules](BUSINESS-RULES.md): constraints and lifecycle decisions.
- `ERD.svg`: ready-to-open vector preview.
- `views/`: smaller diagrams for detailed reading where supplied.

## Owned tables

| Table | Scope | Purpose |
| --- | --- | --- |
| MAP_VERSION | Core | One immutable published routing/map dataset covering a mall. |
| FLOOR_MAP | Core | Visual map assets and image-to-world transform for a floor/version. |
| NAVIGATION_NODE | Core | Graph vertex at a shop entrance, corridor junction, checkpoint, or connector. |
| VERTICAL_CONNECTOR | Core | Physical staircase, lift, or escalator shared across map versions. |
| CONNECTOR_STOP | Core | Graph location where a vertical connector serves a floor in one version. |
| NAVIGATION_EDGE | Core | A directed traversable connection; reverse travel needs a second edge. |
| QR_CHECKPOINT | Core | Stable printed QR destination, surviving map republishing. |
| CHECKPOINT_ANCHOR | Core | Version-specific pose and node attached to a stable QR checkpoint. |
| SHOP_ENTRANCE | Core | Connects a physical shop unit to a version-specific entrance node. |
| FACILITY_ANCHOR | Core | Connects a facility destination to a version-specific graph node. |
| AR_REFERENCE_ANCHOR | Prototype | Optional visual target or VPS reference aligned to the local map. |
| ROUTE_SESSION | Optional | Optional saved route request for troubleshooting or opt-in analytics. |
| ROUTE_STEP | Optional | Ordered graph path in a saved route session. |

## External references

| Referenced table | Owner |
| --- | --- |
| APP_USER | Accounts and Access |
| FACILITY | Mall and Shop Directory |
| FLOOR | Mall and Shop Directory |
| MALL | Mall and Shop Directory |
| SHOP_UNIT | Mall and Shop Directory |

## Design explanation

Physical directory records are stable; routing nodes belong to a MAP_VERSION. One version contains every included floor. Directed edges connect nodes on the same floor or via VERTICAL_CONNECTOR stops across floors. Add a reverse edge where travel is allowed both ways. Filter inaccessible nodes/edges and closed edges before routing.

A printed QR points to QR_CHECKPOINT.public_slug. The backend resolves the current published MAP_VERSION and then CHECKPOINT_ANCHOR. A new map version can use different node IDs without replacing printed codes. Persisted routes keep their original map version. Destination selection resolves SHOP -> SHOP_UNIT -> SHOP_ENTRANCE -> NAVIGATION_NODE.

LiDAR scans and 3D models are assets, not automatically a routing graph or tracking system. Graph authoring, map calibration, and physical anchor validation are separate tasks. AR_REFERENCE_ANCHOR stores measured alignment information; the chosen runtime must implement camera pose estimation, tracking, permission handling, drift recovery, and re-anchoring. The 30/70 dashboard/AR layout and hide-AR toggle are UI requirements and need no extra tables.

Coordinate convention: mall-local right-handed x/y horizontal and z up, all distances in metres. For a floor image pixel (u,v), form dx=(u-pixel_origin_x)*metres_per_pixel and dy=-(v-pixel_origin_y)*metres_per_pixel. Rotate (dx,dy) by rotation_deg counterclockwise and add (world_origin_x_m,world_origin_y_m). z comes from measured floor elevation. For GLTF/WebXR or another runtime, explicitly convert its axis convention to this one; never silently swap y/z.

Publication gate: every active checkpoint and routable destination has a compatible anchor, all edge endpoints share the version, vertical edges match connector stops, and supported routes have passed route tests. A disconnected optional area can remain unreachable if clearly reported. ROUTE_SESSION and ROUTE_STEP are optional history, not prerequisites for computing routes.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.
