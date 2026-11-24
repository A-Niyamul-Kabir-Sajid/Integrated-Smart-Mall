# 40 Blender map pipeline (scan to map package)

**Covers:** EPIC-05 Maps & Navigation, Jira ISM-57 / ISM-58, tools in `tools/blender/` (MapsNavigation)
**Automated tests:** `tests/Blender/test_ism_map.py` (run inside Blender), `tests/Unit/MapsNavigation/MapPackageContractTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 25 minutes

## What you are proving

A LiDAR scan (here a synthetic one with a known layout) becomes a level, squared floor with a sensible draft navigation graph. You can place QR checkpoints, shop entrances, lifts and facilities in Blender. Wrong markers are refused, and the export writes a map package whose plan images, graph and QR headings match the building. There is no web page in this slice; everything happens in a terminal and in Blender.

## Setup

1. Blender 4.2 or newer. Check in PowerShell: `Test-Path "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"`. If it prints `False`, install it with `winget install --id BlenderFoundation.Blender` (about 0.4 GB download, 0.9 GB on disk) and use the folder it reports.
2. Open PowerShell in the project folder and run:

```powershell
$blender = "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"
$work = "tools/blender/work/manual40"
Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
```

No database, web server or accounts are needed.

### Create the data this file needs

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- make-test-scan --out $work
```

**Expect:** the last lines say `test scans written to tools/blender/work/manual40`. The folder holds `test_scan_G.glb`, `test_scan_1.glb` and `truth.json`.

## Steps

### 1. Prepare floor G

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- prepare --blend $work/mall.blend --scan $work/test_scan_G.glb --floor G --elevation 0 --name "Ground floor"
```

**Expect:** `removed 40 floating fragments`, `floor detected at z=1.370 m; rotating -17.00 deg (wall alignment strength 1.00)` and `saved .../mall.blend`. The test scan was saved turned by 17 degrees and lifted by 1.37 m; prepare undoes exactly that.

### 2. Prepare floor 1 and refuse a duplicate

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- prepare --blend $work/mall.blend --scan $work/test_scan_1.glb --floor 1 --elevation 5 --name "First floor"
& $blender -b --factory-startup -P tools/blender/ism_map.py -- prepare --blend $work/mall.blend --scan $work/test_scan_1.glb --floor 1 --elevation 5
echo $LASTEXITCODE
```

**Expect:** the first command says `rotating -25.00 deg` and saves. The second prints `ERROR Floor 1 already has a scan. Use --replace to swap it.`, and `echo` prints `1`. The file is not changed.

### 3. Draft both graphs

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- draft-graph --blend $work/mall.blend --floor G
& $blender -b --factory-startup -P tools/blender/ism_map.py -- draft-graph --blend $work/mall.blend --floor 1
& $blender -b --factory-startup -P tools/blender/ism_map.py -- draft-graph --blend $work/mall.blend --floor G
```

**Expect:** about `draft graph G: 13 vertices, 13 edges over 528.5 m2 walkable` and `draft graph 1: 8 vertices, 7 edges`. The third command is refused: `Floor G already has a graph. Use --replace ...`, so hand edits are never overwritten by accident.

### 4. Look at it in Blender

1. `& $blender $work/mall.blend` (Blender opens).
2. Hover over the 3D View, press numpad 7 (top view) and Alt+Z (X-ray). In the Outliner, hide `ISM Floor 1` (eye icon).
3. Look at the grey scan and the red `NavGraph G` lines.

**Expect:** the corridor runs straight along the screen's x axis, not slanted. The red line runs down the middle of the long corridor, branches into the side corridor and through the doorways into the shops, and splits into two lanes around the bench. No red line enters a pillar or the narrow gaps between the pillars and the wall.

### 5. Add the sidebar tab and place markers

1. Switch an area to the Text Editor, Open `tools/blender/ism_map.py`, press Run Script (the play icon). Back in the 3D View press N: an **ISM Map** tab appears. It lists `G 0 m scan yes graph yes` and `1 5 m scan yes graph yes`.
2. Click **Validate**.

**Expect:** a red message `1 error(s) ...`. Open the text `ISM report` in the Text Editor: `The graph is split into separate pieces: ... on G; ... on 1. Join them with edges or connector stops.` The two floors are not joined yet.

3. Floor 1 still hidden: Shift+right-click on the floor at the far end of the side corridor, just in front of the closed lift box. Click **Connector stop**. Set `connector` to `Central Lift`, leave `connector_type` `elevator` and `direction` `both`.
4. Hide `ISM Floor G` and show `ISM Floor 1`. Shift+right-click on the same spot on floor 1 (the 3D cursor lands at height 5). Click **Connector stop** again with the same values.
5. Validate.

**Expect:** `0 error(s)`, with warnings that no QR checkpoints and no shop entrances are placed yet.

6. Show only floor G again. Shift+right-click on the corridor floor near the west end. Click **Facility**. The tab shows `facility_name CHANGE-ME`, `facility_type toilet`. Validate.

**Expect:** `1 error(s)`. The report says `set ism_facility_name to the facility's name in the app`.

7. Set `facility_name` to `Toilets G West`. Validate: `0 error(s)` again.
8. QR checkpoint: in the top view, Shift+right-click on the floor right against the south face of a pillar in the north row (the row nearer the side corridor). In the sidebar's View tab set 3D Cursor Location Z to `1.4`. Click **QR checkpoint**: the arrow points along +x. Press R Z -90 Enter so it points away from the pillar into the corridor (screen down). Set `code` to `G-C01` and `label` to `Pillar north row, south face`. Validate.

**Expect:** no error for the checkpoint. If the arrow points into the pillar, a warning says `a surface is right in front of the QR; the arrow may point into the pillar`: rotate it 180 degrees and validate again. If the cursor ended up more than 0.35 m from the pillar face, a warning says `no wall or pillar surface within 0.35 m behind the QR`: move it closer (G Y).

### 6. Refusals

Do each one, Validate, read `ISM report`, then undo it (Ctrl+Z until it is gone):

| Try | Expect in ISM report |
|---|---|
| Duplicate the checkpoint (Shift+D, move it to another pillar face) without changing its code | `Checkpoint code G-C01 is used 2 times` |
| Set `connector_type` of both Central Lift stops to `escalator` (direction still `both`) | `Escalator 'Central Lift' must run 'up' or 'down'` |
| Change both to `escalator` and `up`, then add a QR checkpoint `1-C01` (label anything) on a floor-1 pillar face at height 6.4 | `From checkpoint 1-C01 these cannot be reached (check escalator directions): facility Toilets G West` |
| Add a Shop entrance far outside the building, more than 6 m from any red line, with `unit_code` `G-FAR` | `is ... m from the nearest graph edge` |
| Set the facility's `facility_type` to `kiosk` | `must be one of toilet, prayer_room, ...` |
| Tilt the checkpoint arrow to point down (R X 90) | `the arrow must point sideways out of the QR face` |

Nothing is exported while errors remain.

### 7. Export

1. Save the file (Ctrl+S) and close Blender.
2. Run:

```powershell
& $blender -b --factory-startup -P tools/blender/ism_map.py -- export --blend $work/mall.blend --out $work/package
```

**Expect:** `valid` and `package written to ...` with counts. `$work/package` holds `map_package.json`, `report.json` and `floors/` with `G_plan.png`, `G_graph_preview.png`, `G_model.glb` and the same for floor 1.

3. Open `floors/G_plan.png`: light floor, dark walls and solid dark pillars, transparent outside. Open `floors/G_graph_preview.png`: the graph in red, the facility orange, the checkpoint blue, the lift stop purple.
4. Open `map_package.json`. Find your checkpoint under `checkpoints`. `yaw_deg` is about `270` (facing -y, south). `marker_height_m` is `1.4`. Its `node_code` is `G-C01`, and in `nodes` that node lies about 0.8 m in front of the pillar.

### 8. Automatic tests

```powershell
& $blender -b --factory-startup --python-exit-code 1 -P tests/Blender/test_ism_map.py
php artisan test --compact tests/Unit/MapsNavigation
```

**Expect:** `65 passed, 0 failed` from Blender, and 8 passing PHP tests.

## Things that must be refused

| Who | Try | Expect |
|---|---|---|
| mapper | prepare a second scan for floor 1 without `--replace` | `ERROR Floor 1 already has a scan`, exit code 1, file unchanged |
| mapper | draft-graph on a floor that has a graph, without `--replace` | refused, the edited graph is kept |
| mapper | `draft-graph --blend $work/missing.blend --floor G` | `does not exist. Run prepare first.`, exit code 1 |
| mapper | export with a marker still named `CHANGE-ME` | export stops, `report.json` lists the error, no `map_package.json` written |
| mapper | floors joined only by an up escalator, with a QR checkpoint upstairs | `cannot be reached (check escalator directions)` |
| mapper | validate before any lift, escalator or stair joins the floors | `The graph is split into separate pieces` |

## If something looks wrong

- `'blender' is not recognized`: use the full path in `$blender` (Setup step 2).
- The tab does not appear: run the script again from the Text Editor; it registers the tab each time.
- A floor looks turned 90 degrees compared with the other: prepare it again with `--replace --yaw-offset 90`.
- Export says the graph is split into pieces: join the pieces with an edge (select two points, F) or check that lift stops exist on both floors.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
