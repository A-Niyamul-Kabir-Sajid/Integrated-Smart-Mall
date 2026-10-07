# Maps and Navigation — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## MAP_VERSION

One immutable published routing/map dataset covering a mall.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| map_version_id | uuid | PK | No | — | Dataset identifier. |
| mall_id | uuid | FK | No | MALL.mall_id | Mapped mall. |
| version_no | integer | — | No | — | Monotonic version within the mall. |
| status | varchar(16) | — | No | — | draft, published, or retired. |
| coordinate_frame | varchar(80) | — | No | — | Declared mall-local frame name and convention. |
| created_at | timestamptz | — | No | — | Draft creation time. |
| published_at | timestamptz | — | Yes | — | Publication time after validation. |

Primary key: `(map_version_id)`.

Additional unique keys: `(mall_id, version_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FLOOR_MAP

Visual map assets and image-to-world transform for a floor/version.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| floor_map_id | uuid | PK | No | — | Floor map asset identifier. |
| map_version_id | uuid | FK | No | MAP_VERSION.map_version_id | Owning dataset. |
| floor_id | uuid | FK | No | FLOOR.floor_id | Physical floor represented. |
| plan_2d_key | text | — | No | — | Floor plan image or vector storage key. |
| model_3d_key | text | — | Yes | — | Optional GLB/GLTF storage key. |
| pixel_origin_x | decimal | — | No | — | Image pixel x corresponding to transform origin. |
| pixel_origin_y | decimal | — | No | — | Image pixel y corresponding to transform origin. |
| metres_per_pixel | decimal | — | No | — | Uniform scale for the 2D image. |
| rotation_deg | decimal | — | No | — | Counterclockwise image-to-world rotation after y-axis flip. |
| world_origin_x_m | decimal | — | No | — | World x of the image transform origin. |
| world_origin_y_m | decimal | — | No | — | World y of the image transform origin. |

Primary key: `(floor_map_id)`.

Additional unique keys: `(map_version_id, floor_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## NAVIGATION_NODE

Graph vertex at a shop entrance, corridor junction, checkpoint, or connector.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| node_id | uuid | PK | No | — | Version-specific node identifier. |
| map_version_id | uuid | FK | No | MAP_VERSION.map_version_id | Owning dataset. |
| floor_id | uuid | FK | No | FLOOR.floor_id | Physical floor. |
| node_code | varchar(50) | — | No | — | Human-readable code unique in the version. |
| node_type | varchar(24) | — | No | — | junction, entrance, checkpoint, connector, or facility. |
| x_m | decimal | — | No | — | Mall-local x in metres. |
| y_m | decimal | — | No | — | Mall-local y in metres. |
| z_m | decimal | — | No | — | Mall-local elevation in metres. |
| is_accessible | boolean | — | No | — | Whether accessible routing can use this node. |

Primary key: `(node_id)`.

Additional unique keys: `(map_version_id, node_code)`; `(node_id, map_version_id)`; `(node_id, map_version_id, floor_id)`.

Suggested query indexes: `(map_version_id, floor_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## VERTICAL_CONNECTOR

Physical staircase, lift, or escalator shared across map versions.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| connector_id | uuid | PK | No | — | Physical connector identifier. |
| mall_id | uuid | FK | No | MALL.mall_id | Owning mall. |
| name | varchar(100) | — | No | — | Example East Lift A. |
| connector_type | varchar(16) | — | No | — | stairs, elevator, or escalator. |
| is_accessible | boolean | — | No | — | Wheelchair accessibility of this connector. |

Primary key: `(connector_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## CONNECTOR_STOP

Graph location where a vertical connector serves a floor in one version.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| connector_stop_id | uuid | PK | No | — | Stop identifier. |
| connector_id | uuid | FK | No | VERTICAL_CONNECTOR.connector_id | Physical connector. |
| map_version_id | uuid | FK | No | MAP_VERSION.map_version_id | Version of its graph node. |
| floor_id | uuid | FK | No | FLOOR.floor_id | Served floor. |
| node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Connector node on that floor. |

Primary key: `(connector_stop_id)`.

Additional unique keys: `(connector_id, map_version_id, floor_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## NAVIGATION_EDGE

A directed traversable connection; reverse travel needs a second edge.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| edge_id | uuid | PK | No | — | Directed edge identifier. |
| map_version_id | uuid | FK | No | MAP_VERSION.map_version_id | Owning graph dataset. |
| from_node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Starting node. |
| to_node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Ending node. |
| connector_id | uuid | FK | Yes | VERTICAL_CONNECTOR.connector_id | Required for a cross-floor connector edge. |
| length_m | decimal | — | No | — | Traversable physical length in metres. |
| expected_seconds | decimal | — | No | — | Estimated traversal time, including configured connector delay. |
| is_accessible | boolean | — | No | — | Eligibility for an accessible route. |
| status | varchar(20) | — | No | — | open or temporarily_closed. |

Primary key: `(edge_id)`.

Additional unique keys: `(map_version_id, from_node_id, to_node_id)`; `(edge_id, map_version_id)`.

Suggested query indexes: `(map_version_id, from_node_id, status)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## QR_CHECKPOINT

Stable printed QR destination, surviving map republishing.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| checkpoint_id | uuid | PK | No | — | Physical checkpoint identifier. |
| mall_id | uuid | FK | No | MALL.mall_id | Owning mall. |
| checkpoint_code | varchar(50) | — | No | — | Visible label such as F1-C03. |
| public_slug | varchar(120) | UK | No | — | Unpredictable or descriptive public URL identifier; not a credential. |
| label | varchar(120) | — | No | — | Human-readable location. |
| status | varchar(16) | — | No | — | active or retired. |

Primary key: `(checkpoint_id)`.

Additional unique keys: `(mall_id, checkpoint_code)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## CHECKPOINT_ANCHOR

Version-specific pose and node attached to a stable QR checkpoint.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| checkpoint_id | uuid | PK, FK | No | QR_CHECKPOINT.checkpoint_id | Physical checkpoint. |
| map_version_id | uuid | PK, FK | No | MAP_VERSION.map_version_id | Map version. |
| node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Routing start node. |
| yaw_deg | decimal | — | No | — | Heading about the world z axis, using documented convention. |
| marker_height_m | decimal | — | Yes | — | Physical QR centre height above the floor when used for visual alignment. |

Primary key: `(checkpoint_id, map_version_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## SHOP_ENTRANCE

Connects a physical shop unit to a version-specific entrance node.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| shop_entrance_id | uuid | PK | No | — | Entrance binding identifier. |
| shop_unit_id | uuid | FK | No | SHOP_UNIT.shop_unit_id | Physical destination unit. |
| map_version_id | uuid | FK | No | MAP_VERSION.map_version_id | Map version. |
| node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Entrance graph node. |
| is_primary | boolean | — | No | — | Default entrance for this unit in this version. |

Primary key: `(shop_entrance_id)`.

Additional unique keys: `(shop_unit_id, map_version_id, node_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FACILITY_ANCHOR

Connects a facility destination to a version-specific graph node.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| facility_id | uuid | PK, FK | No | FACILITY.facility_id | Physical facility. |
| map_version_id | uuid | PK, FK | No | MAP_VERSION.map_version_id | Map version. |
| node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Destination node. |

Primary key: `(facility_id, map_version_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## AR_REFERENCE_ANCHOR

Optional visual target or VPS reference aligned to the local map.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| ar_anchor_id | uuid | PK | No | — | AR reference identifier. |
| map_version_id | uuid | FK | No | MAP_VERSION.map_version_id | Aligned map version. |
| node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Nearby routing node; pose below is the exact reference pose. |
| reference_type | varchar(20) | — | No | — | image_target, fiducial, or vps_reference. |
| reference_key | text | — | No | — | Target asset key or provider reference identifier. |
| x_m | decimal | — | No | — | Anchor origin x in map coordinates. |
| y_m | decimal | — | No | — | Anchor origin y in map coordinates. |
| z_m | decimal | — | No | — | Anchor origin z in map coordinates. |
| yaw_deg | decimal | — | No | — | Rotation about z. |
| pitch_deg | decimal | — | No | — | Rotation about y. |
| roll_deg | decimal | — | No | — | Rotation about x. |
| physical_width_m | decimal | — | Yes | — | Measured target width, required for metric visual target alignment. |
| status | varchar(16) | — | No | — | draft, validated, or retired. |

Primary key: `(ar_anchor_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## ROUTE_SESSION

Optional saved route request for troubleshooting or opt-in analytics.

Scope: **Optional**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| route_session_id | uuid | PK | No | — | Saved route request identifier. |
| user_id | uuid | FK | Yes | APP_USER.user_id | Optional signed-in user; avoid saving guest identity. |
| map_version_id | uuid | FK | No | MAP_VERSION.map_version_id | Pinned graph version. |
| start_node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Resolved start node. |
| destination_node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Resolved final node. |
| route_mode | varchar(20) | — | No | — | shortest_distance, fastest, or accessible_distance. |
| algorithm | varchar(12) | — | No | — | astar or dijkstra. |
| status | varchar(16) | — | No | — | computed, unreachable, cancelled, or completed. |
| created_at | timestamptz | — | No | — | Request time. |
| expires_at | timestamptz | — | No | — | Retention expiry. |

Primary key: `(route_session_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## ROUTE_STEP

Ordered graph path in a saved route session.

Scope: **Optional**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| route_session_id | uuid | PK, FK | No | ROUTE_SESSION.route_session_id | Owning saved route. |
| step_no | integer | PK | No | — | Zero-based path sequence. |
| node_id | uuid | FK | No | NAVIGATION_NODE.node_id | Node at this step. |
| incoming_edge_id | uuid | FK | Yes | NAVIGATION_EDGE.edge_id | Edge from the previous step; null only at step zero. |

Primary key: `(route_session_id, step_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.
