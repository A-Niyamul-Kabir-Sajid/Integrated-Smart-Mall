# 44 The first real scan as the demo mall's ground floor (demo markers)

**Covers:** EPIC-05 Maps & Navigation, Jira ISM-57, `tools/blender/samples/pilot_ground_markers.py`, the map service and the Smart Mall app's Map page (decisions D-019, D-020, D-021)
**Automated tests:** none for this file's data: the scan is not in git (`tools/blender/work/` is ignored). The pipeline, import, routes and Map page it uses are covered by `tests/Blender/test_ism_map.py`, `map-service/tests/Feature/*` and `tests/Feature/Modules/MapsNavigation/*`.
**Needs:** nothing else; this file is self-contained, but it needs the scan file `tools/blender/work/scans/Scaniverse 2026-11-22 151619.glb` on your computer (ask the team for it; it is not in git because it shows a real interior)
**Time:** about 25 minutes

## What you are proving

The first real iPhone LiDAR scan becomes a routable ground floor: the pipeline prepares it, the marker script places two QR codes, four shop doors and three facilities on it without warnings, the map service imports it for the demo mall, the Smart Mall app routes on it in 2D, and the map service's viewer shows the scan in 3D with its real colours.

## Setup

1. PHP 8.3 or newer, Composer, Node, Chrome or Edge, and Blender 4.2 or newer at `C:\Program Files\Blender Foundation\Blender 5.3\blender.exe` (if missing: `winget install --id BlenderFoundation.Blender`).
2. The scan file at `tools/blender/work/scans/Scaniverse 2026-11-22 151619.glb`.
3. Map service, in the project folder (`migrate:fresh` **deletes the map service's own data**):

```powershell
cd map-service
composer install
if (-not (Test-Path .env)) { copy .env.example .env; php artisan key:generate }
php artisan migrate:fresh --seed --force
php artisan api-key:create "Smart Mall app" --ability=maps:read --origin=http://127.0.0.1:8000
cd ..
```

**Expect:** a key starting `ism_`, and `Embedded map allowed on: http://127.0.0.1:8000`.

4. Smart Mall app, in the project folder (`migrate:fresh` **deletes local data**): in `.env` set `MAP_SERVICE_URL=http://127.0.0.1:8100` and `MAP_SERVICE_KEY=<the ism_ key>`, then `php artisan config:clear`, `php artisan migrate:fresh --seed` and `npm run build`.
5. Start both, each in its own PowerShell window, and keep them open: in `map-service` `php artisan serve --host=127.0.0.1 --port=8100`; in the project folder `php artisan serve` (<http://127.0.0.1:8000>).

Accounts: visitors need none. Map service administrator: `admin@map.test`, password `password`, at <http://127.0.0.1:8100/login>.

## Steps

### 1. Prepare the scan and draft the graph

In PowerShell, in the project folder:

```powershell
$blender = "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"
$work = "tools/blender/work/real-ground"
Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
& $blender -b --factory-startup -P tools/blender/ism_map.py -- prepare --blend $work/mall.blend --scan "tools/blender/work/scans/Scaniverse 2026-11-22 151619.glb" --floor G --elevation 0 --name "Ground floor"
& $blender -b --factory-startup -P tools/blender/ism_map.py -- draft-graph --blend $work/mall.blend --floor G
```

**Expect:** `removed 279 floating fragments`, `rotating -0.41 deg`, `floor G: 231275 triangles at elevation 0.0 m`, then `draft graph G: 3 vertices, 2 edges over 26.9 m2 walkable`.

### 2. Place the demo markers and export

```powershell
& $blender -b $work/mall.blend --factory-startup --python-exit-code 1 -P tools/blender/samples/pilot_ground_markers.py
& $blender -b --factory-startup -P tools/blender/ism_map.py -- export --blend $work/mall.blend --out $work/package
```

**Expect:** nine `[pilot]` lines and `9 markers placed and saved`; then `valid` with **no** `warning` line, and `{'floors': 1, 'nodes': 17, 'edges': 32, 'checkpoints': 2, 'shop_entrances': 4, 'facility_anchors': 3, 'connectors': 0}`.

3. Open `tools/blender/work/real-ground/package/floors/G_graph_preview.png`.

**Expect:** the L-shaped floor with a red line up the corridor; two blue dots (QR codes, one near the south end, one in the north room), four green dots (doors) and three orange dots (washroom on the west side, information desk in the north room, entrance at the south end), each joined to the red line.

### 3. Import it for the demo mall

```powershell
cd map-service
php artisan map:import ../tools/blender/work/real-ground/package --name="Real scan, ground floor (demo markers)" --mall=niagara-city-mall --publish
cd ..
php artisan maps:service-check
```

**Expect:** `Imported “Real scan, ground floor (demo markers)” ... (published)` and `Floors: G · graph: 17 nodes, 32 edges`; the check lists the map.

### 4. Route on it in the Smart Mall app

1. Sign in at <http://127.0.0.1:8100/login> as `admin@map.test` and open <http://127.0.0.1:8100/admin/checkpoints>. Copy the address of `G-C01` ("South end, east wall (demo)") and open it.

**Expect:** the Smart Mall Map page: "You are at: South end, east wall (demo)", the scanned floor plan with a green **You are here** dot near the south end, and labels G-01 to G-04, Washroom G (demo), Information desk (demo), South entrance (demo).

2. In the frame choose To "Shop G-04", click **Show route**.

**Expect:** "1 min · 17 m · Ground floor"; a blue line up the corridor into the north room ending at a red dot by G-04; steps: Start at South end, east wall (demo). · Walk 12 m. · Bear right and walk 3 m. · Turn right and walk 2 m. · Arrive at Shop G-04.

3. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops>, open **Fresh Mart**, click **Show on map** next to "Unit G-02".

**Expect:** the Map page with To "Shop G-02". Choose From "North room, north wall (demo) (G)" and **Show route**: about 9 m, ending at the G-02 dot.

4. Choose To "Nearest: Toilet" and **Show route**.

**Expect:** about 11 m; the line ends at the "Washroom G (demo)" dot and the last step is "Arrive at the nearest toilet."

### 5. See the scan in 3D

1. On <http://127.0.0.1:8100/admin/maps> click **Open 3D editor** on "Real scan, ground floor (demo markers)" (wait a few seconds: the floor model is about 13 MB).

**Expect:** the scanned corridor in 3D with its real photo colours, the red navigation graph on the floor, blue QR markers, green door markers and orange facility markers. The panel says 1 floor, 17 nodes, 32 edges, 2 QR codes, 4 entrances.

2. Click **Top**.

**Expect:** the L-shape seen from above, matching the 2D plan of step 4.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| (pipeline) | In `pilot_ground_markers.py` move `G-C01` back to `(1.3, 2.0, 1.4)` (in the middle of the corridor), run step 2 again | the export warns "no wall or pillar surface within 0.35 m behind the QR; move it onto the surface" (put it back afterwards) |
| (pipeline) | Change a door's unit code to `CHANGE-ME` and run step 2 | the export stops with an error about a placeholder code; no package is written |
| visitor | <http://127.0.0.1:8000/malls/niagara-city-mall/map?to=unit:G-05> | "That place is not on the map. Choose it from the list." (G-05 has no door on this map) |
| visitor | To "Shop G-04" with **Step-free** ticked | the same route: this floor has no stairs or escalators |

## If something looks wrong

- `prepare` numbers differ: another copy or export of the scan was used; the floor and rotation lines must still appear.
- A warning in step 2: a marker is off its wall or blocked; read the warning and adjust its place in `tools/blender/samples/pilot_ground_markers.py`.
- The Map page says "This mall has no indoor map yet.": step 3 was not run with `--mall=niagara-city-mall --publish`.
- The 3D view stays empty: wait for the model to load, or click **Fit**.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
