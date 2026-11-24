# 45 Map service: end points with keys

**Covers:** EPIC-11 / EPIC-05, Jira ISM-114, the separate app `map-service/` and the Smart Mall app's Map page (decision D-022)
**Automated tests:** `map-service/tests/Feature/EndPointTest.php` (11), `tests/Feature/Modules/MapsNavigation/MallMapPageTest.php` (`point:` destination)
**Needs:** nothing else; this file is self-contained
**Time:** about 25 minutes

## What you are proving

A map administrator adds an end point (a destination such as a kiosk) by clicking the floor in the 3D editor or with the form on the End points page. The map service shows its key, joins it to the nearest walkway and keeps the key for new map versions. Visitors are routed to it with `point:<key>`. Points too far from any walkway are refused, closing the walkway reaches the end point too, and deleting it leaves the map's own graph as it was.

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

## Steps

### 1. Add an end point in the 3D editor

1. Sign in at <http://127.0.0.1:8100/login>, open <http://127.0.0.1:8100/admin/maps>, click **Open 3D editor** on "Test mall", then **Top**.
2. Click **＋ End point**. **Expect:** the hint "Click the floor where the end point goes (Esc to stop)".
3. Click the floor of the main corridor, close to the red line, on the ground floor. In the box that asks for a name type `Kiosk` and press OK.

**Expect:** in the left panel under **End points (1)**: "New end point “Kiosk”, key:" and a 10-character key in large purple letters (for example `k7m2x9q4ab`), "Route to it with point:<key>"; below it a row with the key, **Copy** and **Delete**. On the floor a purple pin labelled `<key> · Kiosk` with a dashed line to the red graph. The bottom message says "End point “Kiosk” added with key <key>."

4. Click **＋ End point** again and click the floor far outside the building (on the grid), if the click lands on the scan; otherwise skip.

**Expect:** a message starting "No walkway of floor G is within 6 m of this point."; nothing is added.

### 2. The End points page

1. Click **Manage end points** (or <http://127.0.0.1:8100/admin/maps>, **End points** on "Test mall").

**Expect:** the Kiosk row: the key in a dashed box with **Copy**, the name with **Rename**, `point:<key>` and "open in the Smart Mall app", "floor G · x … · y …", **on the map** and its distance from the walkway.

2. Add one with the form: Name `Meeting point`, Floor `G · Ground floor`, x `20`, y `16`, **Add end point**.

**Expect:** "End point “Meeting point” added. Its key is <key>." and a second row.

3. Add one with x `200`, y `200`.

**Expect:** "No walkway of floor G is within 6 m of this point. Place it closer to the navigation graph." and no new row.

4. Rename "Meeting point" to `Meeting point by the stairs`, **Rename**. **Expect:** the new name; the key is unchanged.

### 3. Route to it in the Smart Mall app

1. On the Kiosk row click **open in the Smart Mall app**.

**Expect:** <http://127.0.0.1:8000/malls/niagara-city-mall/map?to=point%3A...> with **To** "Kiosk (G)" (the Places group of the list). Choose From "Pillar P3 north row, south face (G)", **Show route**: the line ends at the purple Kiosk dot and the last step is "Arrive at Kiosk."

2. Choose From "Side corridor, west wall by the lift (1)" (first floor), **Show route**.

**Expect:** the route takes the lift (or stairs) down and ends at the Kiosk.

### 4. Closures reach the end point

1. In the map service open **Closures** on "Test mall". In the 3D editor, note the two red-graph nodes on either side of the Kiosk's dashed line (hover shows positions; node codes are listed on the Closures page). Close the walkway between them (both directions).
2. Ask the route of step 3.1 again.

**Expect:** the route reaches the Kiosk from the other end of that walkway, or the map says there is no open route if both ends are closed off. Reopen the walkway afterwards.

### 5. A new map version keeps the keys

```powershell
cd map-service
php artisan map:import ../tools/blender/work/manual45/package --name="Test mall v2" --mall=niagara-city-mall --publish
cd ..
```

1. Open **End points** on "Test mall v2".

**Expect:** Kiosk and Meeting point with the **same keys** as before, both **on the map**. The Smart Mall link of step 3.1 still works.

### 6. Delete

1. On the End points page of "Test mall v2" click **Delete** on Kiosk and confirm.

**Expect:** "End point “Kiosk” (<key>) deleted; links that use its key no longer find it." Opening the step 3.1 link again shows "That place is not on the map. Choose it from the list." The rest of the map routes as before.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| signed out | <http://127.0.0.1:8100/admin/maps/{id}/end-points> | sent to the sign-in page |
| map admin | Add with an empty name or x `abc` | errors on the name and x; nothing added |
| map admin | Add more than 6 m from every walkway | "No walkway of floor … is within 6 m of this point." |
| visitor | <http://127.0.0.1:8000/malls/niagara-city-mall/map?to=point:zzzzzzzzzz> | "That place is not on the map. Choose it from the list." |
| API, no key | `curl.exe -s "http://127.0.0.1:8100/api/v1/malls/niagara-city-mall/routes?from=node:G-C01&to=point:<key>"` | 401 |

## If something looks wrong

- No **＋ End point** button: the 3D editor was opened as a file viewer; open it from the Maps page while signed in.
- "not on the map" after importing a new version: that version has no walkway within 6 m of the point; delete it and add it again in the new place.
- The click asks for no name: the click missed the scan (grid only); click on the floor.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
