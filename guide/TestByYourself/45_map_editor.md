# 45 Map editor: points, QR codes, paths and the navigation graph

**Covers:** EPIC-11 / EPIC-05, Jira ISM-114 and ISM-115, the separate app `map-service/` and the Smart Mall app's Map page (decision D-022)
**Automated tests:** `map-service/tests/Feature/MapEditTest.php` (23), `map-service/tests/Feature/GraphEditTest.php` (11), `tests/Feature/Modules/MapsNavigation/MallMapPageTest.php` (`point:` destination)
**Needs:** nothing else; this file is self-contained
**Time:** about 40 minutes

## What you are proving

A map administrator adds what the scan missed and corrects the navigation graph, on the "Points and paths" page and in the 3D editor:

- points of four kinds (node, shop entrance, facility, QR code), each with a unique key and a height above the floor (1.5 m, QR codes 1.4 m, changeable);
- walkways drawn as paths, whose corners join nodes and path ends, and paths merged at their ends;
- changes to the graph itself: move or remove a node, connect two nodes, make a walkway one way or not step-free, remove it, and undo any change.

Visitors in the Smart Mall app are routed to the new places, and every edit survives a rebuild and a new map version.

## Setup

1. PHP 8.3 or newer, Composer, Node, Chrome or Edge (WebGL), and Blender 4.2 or newer at `C:\Program Files\Blender Foundation\Blender 5.3\blender.exe` (if missing: `winget install --id BlenderFoundation.Blender`).
2. In PowerShell, in the project folder, build the two-floor test mall (the Blender pipeline's own test, about 15 seconds):

```powershell
$blender = "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"
Remove-Item -Recurse -Force tools/blender/work/manual45 -ErrorAction SilentlyContinue
& $blender -b --factory-startup --python-exit-code 1 -P tests/Blender/test_ism_map.py -- --keep tools/blender/work/manual45
```

**Expect:** `66 passed, 0 failed`.

3. Map service (`migrate:fresh` **deletes the map service's own data**):

```powershell
cd map-service
composer install
if (-not (Test-Path .env)) { copy .env.example .env; php artisan key:generate }
php artisan migrate:fresh --seed --force
php artisan map:import ../tools/blender/work/manual45/package --name="Test mall" --mall=niagara-city-mall --publish
php artisan api-key:create "Smart Mall app" --ability=maps:read --origin=http://127.0.0.1:8000
cd ..
```

4. Smart Mall app (`migrate:fresh` **deletes local data**): in `.env` set `MAP_SERVICE_URL=http://127.0.0.1:8100` and `MAP_SERVICE_KEY=<the ism_ key>`, then `php artisan config:clear`, `php artisan migrate:fresh --seed`, `npm run build`.
5. Start both, each in its own window: in `map-service` `php artisan serve --host=127.0.0.1 --port=8100`; in the project folder `php artisan serve`.

Account (map service): `admin@map.test`, password `password`, at <http://127.0.0.1:8100/login>. Visitors in the Smart Mall app need no account.

The ground floor of the test mall has a long corridor along y ≈ 11 with nodes `G-N0001` (3.2, 11.1) … `G-N0016` (57.8, 11.1), the QR code `G-C01` at `G-N0006` (26.1, 11.5), a side corridor north from `G-N0007` (30.1, 12.0) to the lift, and the shops `G-101` (west) and `G-201` (east).

## Steps

### 1. Points on the page, with keys and heights

1. Sign in at <http://127.0.0.1:8100/login>, open <http://127.0.0.1:8100/admin/maps>, click **Points and paths** on "Test mall".
2. **Add a point**: What is it `Node`, Name `Kiosk`, Floor `G`, x `20`, y `16`, leave Height `1.5`, **Add point**.

**Expect:** "Node “Kiosk” added. Its key is <10 letters and digits>." The Points table shows the key in a dashed box with **Copy**, `point:<key>`, "open in the Smart Mall app", "floor G · x 20.00 · y 16.00 · 1.50 m high", **on the map** 4.9 m from the walkway.

3. Add a **Shop entrance**: Name `Phone Fix door`, unit `G-07`, tick **Main door**, x `40`, y `15.5`. **Expect:** on the map, 3.1 m from the walkway; "unit G-07 · main door".
4. Add a **QR code**: Name `Pillar P5 north row, south face`, QR code `G-C10`, x `38`, y `18.7`, Facing `270`; the Height field changes to **1.4** by itself. **Add point**.

**Expect:** the row shows "facing 270°", "1.40 m high" and "QR address http://127.0.0.1:8000/malls/niagara-city-mall/map/qr/<slug>". <http://127.0.0.1:8100/admin/checkpoints> lists `G-C10` as active and on the published map.

5. Add a **Facility**: Name `Washroom East`, type `Toilet`, x `50`, y `19`. **Expect:** "not connected — no walkway within 6 m: draw a path to it" (the next step joins it).

### 2. Edit a point

1. In the Kiosk row open **Edit**, set Height `2.2`, x `21`, **Save point**.

**Expect:** "Node “Kiosk” (<same key>) saved."; "x 21.00 · … · 2.20 m high". The key did not change.

2. Open **Edit** on `Phone Fix door`, untick **Main door**, save; then add another shop entrance for `G-07` with **Main door** ticked at x `41`, y `15.5`.

**Expect:** both saved. Trying to tick **Main door** again on the first one says "Unit G-07 already has a main door on this map".

### 3. Paths: draw, snap, edit, merge

1. **Add a path** on floor G with corners `53.1, 10.8` and `52, 18` and `50, 19`, Name `To the washroom`.

**Expect:** "Path <key> added: … m with 3 corners." The path row says **joined to the map** (its first corner is node `G-N0015`), and `Washroom East` now says **on the map** (joined by a path).

2. **Add a path** with corners `50, 19` and `46, 19` (it starts at the end of the first one).
3. Open **Edit** on `To the washroom`, choose the second path under "Merge with the path that meets this one at an end", **Merge into <key>**.

**Expect:** "Path <second key> merged into <first key>: … m with 4 corners."; one path left.

4. Open **Edit** on it, untick **Step-free**, **Save path**. **Expect:** "not step-free" in its row.

### 4. The navigation graph

1. On the page, under **Navigation graph**: Change `Remove a walkway`, From `G-N0006`, To `G-N0007`, **Apply change**.

**Expect:** "Remove the walkway G-N0006 – G-N0007 — done." and the change listed with its key and **Undo**.

2. Open <http://127.0.0.1:8000/malls/niagara-city-mall/map?from=node:G-N0006&to=unit:G-201>.

**Expect:** the map says there is no open route (the corridor east of the QR code is cut off and the escalator only goes up).

3. Click **Undo** on that change, reload the Smart Mall link. **Expect:** a route east along the corridor to G-201.
4. Change `Connect two nodes`, From `G-N0004`, To `G-N0013`, **Apply change**. **Expect:** "Connect G-N0004 and G-N0013 — done."
5. Change `Change a walkway`, From `G-N0010`, To `G-N0011`, Direction `One way, from → to`, **Apply change**. **Expect:** "Change the walkway G-N0010 – G-N0011: one way G-N0010 → G-N0011".
6. Change `Remove a node`, Node `E-G-201`. **Expect:** refused: "Node E-G-201 is a shop entrance; remove that first."

### 5. The same in the 3D editor

1. On <http://127.0.0.1:8100/admin/maps> click **Open 3D editor**, then **Top**.

**Expect:** the graph drawn as it is now: red walkways, dashed amber for the path that is not step-free, a dashed line with an arrow for the one-way walkway, purple pins for nodes, green for doors, orange for facilities and blue squares for QR codes, each labelled with its key.

2. Click **✎ Graph**, click a red dot in the corridor. **Expect:** a panel "Node G-N00… · junction · floor G · x … · y …" with **Move**, **Connect to…**, **Remove node**. For a door or QR node the panel says why it cannot be removed.
3. Click **Move (click the new place)** and click half a metre away. **Expect:** "Node … moved." and the change in **Graph changes** with **Undo**.
4. Click **✎ Graph** and click a red line. **Expect:** "Walkway A – B · … m" with Step-free, Direction and **Remove walkway**.
5. Click **＋ Point**, click the floor; choose `Facility`, type `Prayer room`, Name `Prayer room G`; the Marker height shows **1.5**; **Save point**. **Expect:** the key shown in large purple letters and an orange pin at 1.5 m.
6. Click **＋ QR code** and click a pillar face. **Expect:** the form says "facing …°" and Height **1.4**; enter code `G-C11` and a label, **Save QR code**: the key and the QR address are shown.
7. Click **＋ Path**, click once on the end of the washroom path (the dot turns **yellow**: "last corner joins the end of path …"), click two more corners, **Finish path**.
8. In **Points**, click **Edit** on `Kiosk`, then **Move (click the floor)**, click a new place, **Save changes**. **Expect:** the pin moves; the key stays.

### 6. Visitors and new versions

1. Copy the Kiosk's key and open <http://127.0.0.1:8000/malls/niagara-city-mall/map?from=node:G-C01&to=point:<key>>. **Expect:** a route that ends "Arrive at Kiosk."
2. In `map-service` run `php artisan map:rebuild-graph`, then import a new version:

```powershell
cd map-service
php artisan map:import ../tools/blender/work/manual45/package --name="Test mall v2" --mall=niagara-city-mall --publish
cd ..
```

**Expect:** on "Test mall v2" → **Points and paths**: the same points (same keys), paths and graph changes. The Smart Mall link of step 6.1 still works.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| signed out | <http://127.0.0.1:8100/admin/maps/{id}/end-points> or `/graph` | sent to the sign-in page |
| map admin | a QR code at Height `5` | "A QR code hangs between 0.2 and 3 m above the floor." |
| map admin | a second facility called `Washroom East` | "A facility called “Washroom East” is already on this map." |
| map admin | a path with one corner | "A path needs 2 to 100 corners." |
| map admin | merge two paths whose ends are far apart | "These paths do not meet at an end: their ends are more than 0.5 m apart." |
| map admin | Connect `G-C01` with `1-C01` | "A walkway joins two nodes of the same floor; …" |
| map admin | Remove the node `G-X-Central-Lift` | "… is a stop of a lift, escalator or stairs; remove that first." |
| visitor | <http://127.0.0.1:8000/malls/niagara-city-mall/map?to=point:zzzzzzzzzz> | "That place is not on the map. Choose it from the list." |

## If something looks wrong

- No **＋ Point**, **＋ QR code**, **＋ Path** or **✎ Graph** buttons: the 3D editor was opened as a file viewer; open it from the Maps page while signed in.
- A click does nothing but a red message says it missed the scan: click on the scanned floor or wall itself.
- "not applied" next to a change after importing a new version: that version no longer has the node codes the change names; undo it and make it again.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
