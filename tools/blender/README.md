# Blender map pipeline

Turns iPhone LiDAR scans of the mall into the **map package** that the MapsNavigation module imports (Jira ISM-57, ISM-58; decision D-018). One Python file, [ism_map.py](ism_map.py), works three ways: from the command line without a window, as a sidebar tab inside Blender, or as an installed add-on.

```
iPhone LiDAR scan (one GLB/OBJ/USDZ per floor)
  -> prepare      clean, level, square to the axes, file under its floor     -> mall.blend
  -> draft-graph  first navigation graph from the walkable area              -> NavGraph <floor> mesh
  -> you          tidy the graph, place QR checkpoints, shop entrances, lifts, facilities
  -> export       validate, then write map_package.json + per-floor plan PNG, preview PNG, GLB
```

How to scan is in [SCANNING.md](SCANNING.md).

## Requirements

- Blender 4.2 or newer; tested with **Blender 5.3.0** (installed with `winget install --id BlenderFoundation.Blender`, about 0.9 GB in `C:\Program Files\Blender Foundation\Blender 5.3`). Blender bundles Python and numpy; nothing else is installed.
- The pipeline is offline authoring. It adds no PHP or JavaScript dependency to the app.

In the commands below `blender` means the full path, for example in PowerShell:

```powershell
$blender = "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"
& $blender -b --factory-startup -P tools/blender/ism_map.py -- <command> [options]
```

Put scans and `.blend` files in `tools/blender/work/`. That folder is ignored by git: scans are large and show shop interiors.

## Try it in two minutes with the test scan

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- make-test-scan --out tools/blender/work/test
& $blender -b --factory-startup -P tools/blender/ism_map.py -- prepare --blend tools/blender/work/test/mall.blend --scan tools/blender/work/test/test_scan_G.glb --floor G --elevation 0 --name "Ground floor"
& $blender -b --factory-startup -P tools/blender/ism_map.py -- prepare --blend tools/blender/work/test/mall.blend --scan tools/blender/work/test/test_scan_1.glb --floor 1 --elevation 5 --name "First floor"
& $blender -b --factory-startup -P tools/blender/ism_map.py -- draft-graph --blend tools/blender/work/test/mall.blend --floor G
& $blender -b --factory-startup -P tools/blender/ism_map.py -- draft-graph --blend tools/blender/work/test/mall.blend --floor 1
& $blender tools/blender/work/test/mall.blend
```

The test scan is a made-up two-floor mall section (a 60 m corridor with pillars, three shops, a side corridor with a lift and a bench). It was saved rotated and shifted, like a real phone scan. Then place markers (step 5 below) and export.

## The real workflow

### 1. Scan

Follow [SCANNING.md](SCANNING.md). You need one mesh file per floor.

### 2. Prepare each floor

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- prepare --blend tools/blender/work/mall.blend --scan tools/blender/work/scans/G.glb --floor G --elevation 0 --name "Ground floor"
```

| Option | Meaning |
|---|---|
| `--floor` | Floor code exactly as in the app (`floors.code`, up to 20 characters) |
| `--elevation` | Floor level in metres in the mall frame. Use the same numbers for every map version, for example 0, 5.0, 10.0 |
| `--scale F` | Multiply the size when your tape-measure check disagrees (see SCANNING.md) |
| `--yaw-offset 90` | Turn the floor a further 90, 180 or 270 degrees after auto-squaring, when it lands turned compared with the floor below |
| `--yaw-deg D` | Use exactly this rotation instead of auto-squaring |
| `--offset X,Y` | Extra shift in metres, to line floors up |
| `--keep-xy` | Do not move the scan's lowest corner to (0, 0) |
| `--ceiling-cut 2.6` | Drop surfaces higher than this above the floor (ceilings, signs) |
| `--min-fragment 0.5` | Drop floating pieces smaller than this (people walking past, noise) |
| `--max-tris 400000` | Working triangle budget |
| `--replace` | Replace this floor's scan |

What it does: imports the file and joins its parts, merges duplicate vertices, deletes floating fragments, finds the floor level (largest up-facing area) and moves it to `--elevation`. It turns the scan so the walls line up with x and y, using the strongest wall direction. It cuts the ceiling, moves the lowest corner to (0, 0), reduces the triangle count, and files the result as `Scan <floor>` in the collection `ISM Floor <floor>`. What it did is stored on the object in the custom property `ism_prepare`, and copied into the export.

Check each floor in Blender: top view (numpad 7), orthographic, X-ray (Alt+Z). Pillars of different floors should sit on top of each other. If a floor is turned or shifted, run prepare again with `--replace` and `--yaw-offset` or `--offset`.

### 3. Draft the navigation graph

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- draft-graph --blend tools/blender/work/mall.blend --floor G
```

It marks floor cells and obstacle cells (anything 0.15 to 2.0 m above the floor) on a 10 cm grid. It then keeps the cells at least `--clearance` (0.35 m) from any obstacle and thins that area to its centre lines. Finally it removes dead-end branches into corners, out-and-back spikes and small islands, keeps branches that go through a doorway into a room, straightens the lines and saves a mesh `NavGraph <floor>`. It refuses to overwrite an existing graph without `--replace`, so your edits are safe.

The draft is a starting point. Open the `.blend`, select `NavGraph G`, press Tab for Edit Mode and fix it:

| Do | How |
|---|---|
| Move a point | select it, G |
| Extend a corridor line | select an end point, E, click |
| Join two points | select both, F |
| Delete | X, then Vertices or Edges |
| Mark edges with steps (not for wheelchairs) | select the edges, Mesh > Set Attribute, `ism_not_accessible` = on |
| Mark edges temporarily closed | select the edges, Mesh > Set Attribute, `ism_closed` = on |

Keep lines in the middle of walkways, put a point at every junction, and never draw an edge through a wall or a shop window. You can draw a graph by hand too: any mesh with the custom properties `ism_role` = `graph` and `ism_floor` = the floor code.

### 4. Open the ISM Map tab

In Blender, open `ism_map.py` in the Text Editor and press **Run Script** (or install it once: Edit > Preferences > Add-ons > Install from Disk). Press N in the 3D View and open the **ISM Map** tab:

1. **Floors**: import a scan as a floor, draft a graph.
2. **Markers at 3D cursor**: click the floor where the marker goes (Shift+right-click puts the 3D cursor there), then add the marker. The tab shows the selected marker's properties for editing.
3. **Check and export**: Validate writes all problems into the text `ISM report` (Text Editor). Export writes the package to the folder shown (`//` means next to the `.blend`).

### 5. Place markers

Every marker is an empty with custom properties. The floor is taken from its height.

| Marker | Place it | Properties |
|---|---|---|
| QR checkpoint | **On the pillar or wall face**, at the QR code's centre and height (usually 1.4 m). The arrow must point **out of the face**, toward where people stand: rotate with R Z in the top view | `ism_code` the code printed on the label, e.g. `G-C03` (unique, one per face); `ism_label` e.g. `Pillar P3, south face`; `ism_qr_width_m` the printed size (0.18) |
| Shop entrance | In the doorway, on the floor | `ism_unit_code` the unit code in the app (`shop_units.unit_code`); `ism_primary` True for the main door (one per unit) |
| Connector stop | Where a lift, escalator or stair meets this floor; one per floor | `ism_connector` the same name on every floor, e.g. `East Lift A`; `ism_connector_type` `elevator`, `escalator` or `stairs`; `ism_direction` `both`, `up` or `down` (an escalator must be `up` or `down`; model the other direction as its own escalator); `ism_accessible` |
| Facility | At the facility door | `ism_facility_name` as in the app; `ism_facility_type` `toilet`, `prayer_room`, `information`, `exit`, `atm`, `lift` or `other` |

On export, each marker gets its own graph node and is linked to the nearest graph edge (at most `ism_link_max_m`, 6 m). A QR checkpoint's node is 0.8 m in front of its face, where the shopper stands when holding the phone up to the code.

### 6. Validate and export

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- validate --blend tools/blender/work/mall.blend
& $blender -b --factory-startup -P tools/blender/ism_map.py -- export --blend tools/blender/work/mall.blend --out tools/blender/work/package
```

Export options: `--mpp 0.05` plan metres per pixel, `--glb-tris 120000` model triangle budget, `--max-texture 2048`, `--draco` (smaller GLB; the viewer then needs three.js `DRACOLoader`). Exit code 1 means validation failed; the reasons are printed and saved in `report.json`.

**Errors (export stops):** a floor without a scan or graph; a marker with `CHANGE-ME` or an invalid code or type; a QR arrow pointing up or down; a marker more than 6 m from the graph; a checkpoint code used twice; more than one primary entrance for a unit; a connector with fewer than two floors or two stops on one floor; an escalator set to `both`; the graph split into pieces; a shop or facility that cannot be reached from some QR checkpoint (for example, floors joined only by an up escalator).

**Warnings:** a link to the graph that the scan blocks; a QR code not sitting on a surface, or facing into one (arrow reversed); a QR code lower than 0.3 m or higher than 2.5 m; a unit with no primary entrance; graph points far from the floor level.

## What the package contains

```
package/
  map_package.json          the contract below
  report.json               validation result
  floors/<code>_plan.png    top-down plan: floor light, walls and pillars dark, outside transparent
  floors/<code>_graph_preview.png   plan with the graph drawn on it (for checking)
  floors/<code>_model.glb   reduced 3D model of the floor, Y-up
```

[samples/pilot_map_package.json](samples/pilot_map_package.json) is a complete example made from the test scan. `tests/Unit/MapsNavigation/MapPackageContractTest.php` checks it against the ERD business rules.

| Key | Content | ERD table |
|---|---|---|
| `coordinate_frame` | `ism-mall-local-v1`: metres, right-handed, x and y horizontal, **z up**, yaw in degrees counterclockwise from +x | MAP_VERSION.coordinate_frame |
| `floors[]` | `floor_code`, `name`, `elevation_m`, `plan` (`file`, `width_px`, `height_px`, `pixel_origin_x/y` = 0 at the top-left corner, `metres_per_pixel`, `rotation_deg` = 0, `world_origin_x_m/y_m`), `model` (`file`, `triangles`, `draco`) | FLOOR_MAP |
| `nodes[]` | `node_code`, `floor_code`, `node_type` (`junction`, `entrance`, `checkpoint`, `connector`, `facility`), `x_m`, `y_m`, `z_m`, `is_accessible` | NAVIGATION_NODE |
| `edges[]` | directed: `from_node_code`, `to_node_code`, `length_m`, `expected_seconds`, `is_accessible`, `status` (`open`, `temporarily_closed`), `connector` (name or null) | NAVIGATION_EDGE |
| `connectors[]` | `name`, `connector_type`, `direction`, `is_accessible`, `stops[]` (`floor_code`, `node_code`) | VERTICAL_CONNECTOR, CONNECTOR_STOP |
| `checkpoints[]` | `checkpoint_code`, `label`, `floor_code`, `node_code`, `qr` (`x_m`, `y_m`, `z_m` of the QR centre, `yaw_deg` direction the face points, `marker_height_m`, `physical_width_m`) | QR_CHECKPOINT, CHECKPOINT_ANCHOR, AR_REFERENCE_ANCHOR (fiducial) |
| `shop_entrances[]` | `unit_code`, `floor_code`, `node_code`, `is_primary` | SHOP_ENTRANCE |
| `facility_anchors[]` | `facility_name`, `facility_type`, `floor_code`, `node_code` | FACILITY_ANCHOR |
| `settings`, `scans`, `stats` | timing settings used, what prepare did to each scan, counts | (audit) |

Conversions:

- **Plan pixel to metres:** `x = world_origin_x_m + px * metres_per_pixel`, `y = world_origin_y_m - py * metres_per_pixel` (py grows downwards). Every floor image of one package has the same size and transform, so floors line up.
- **glTF:** `(gltf_x, gltf_y, gltf_z) = (x, z, -y)`. three.js and WebXR use the same Y-up convention, so a loaded GLB is already in mall metres.
- **AR alignment:** a QR face pointing along `yaw_deg` means a shopper facing the code looks along `yaw_deg + 180`.

Codes: junctions `<floor>-N0001`; checkpoints keep their QR code; entrances `E-<unit>`; connector stops `<floor>-X-<name>`; facilities `<floor>-F-<name>`. All fit varchar(50).

Timing uses scene custom properties (Scene properties > Custom Properties): `ism_walk_mps` 1.2, `ism_elevator_wait_s` 30, `ism_elevator_mps` 1.0, `ism_escalator_mps` 0.5, `ism_stairs_vertical_mps` 0.4, plus `ism_link_max_m` 6 and `ism_checkpoint_standoff_m` 0.8. These are defaults for the team to confirm.

## View it in 3D and add signs and advertisements

Import the package into the separate map service ([map-service/README.md](../../map-service/README.md)) and open its 3D editor. You can also look at a package without the service: start the service, open `http://127.0.0.1:8100/viewer/index.html` and choose "Open package folder…".

## Tests

```powershell
& $blender -b --factory-startup --python-exit-code 1 -P tests/Blender/test_ism_map.py
php artisan test --compact tests/Unit/MapsNavigation
```

The Blender test (about 15 seconds) builds the test scans, runs every command, places markers and checks the results against the known layout. It checks alignment to within centimetres, the junction position, that no graph point is inside a pillar or in the gaps beside the wall, the doorway branches, the validation refusals, the QR headings, escalator direction, routes across floors and the plan pixels. Add `-- --write-sample` to refresh the sample after a deliberate change to the format, then run the PHP test. Manual checks: [guide/TestByYourself/37_blender_map_pipeline.md](../../guide/TestByYourself/37_blender_map_pipeline.md).

## Limits

- The scan must be level (LiDAR apps keep gravity "down"); a tilted scan is not corrected.
- One mesh per floor. The pipeline does not stitch scan sections or line floors up automatically; do that in Blender (SCANNING.md).
- Ramps or mezzanines inside one floor are not modelled; give a mezzanine its own floor code.
- Glass shop fronts and mirrors scan badly; check walls there and draw graph edges by hand.
- The plan image is drawn from the scan, not from architectural drawings, and the draft graph needs a person to check it.
- Importing the package into the app (migrations, models, import command) is ISM-59, not built yet.
