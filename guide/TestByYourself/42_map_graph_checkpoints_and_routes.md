# 42 Map service: navigation graph, QR codes, routes and closures

**Covers:** EPIC-05 Maps & Navigation, Jira ISM-58 / ISM-59 / ISM-61 / ISM-62, the separate app `map-service/` (decision D-021)
**Automated tests:** `map-service/tests/Feature/GraphImportTest.php`, `CheckpointTest.php`, `RoutePlannerTest.php`, `RouteApiTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 30 minutes

## What you are proving

Importing a map stores its navigation graph, refuses a broken graph and keeps one published map per mall. Publishing creates the mall's QR codes with random addresses that survive a new map version. A scanned code resolves to where the visitor stands. Routes go across floors by the fastest way, step-free by the lift, follow escalator direction, find the nearest toilet, visit several stops and go around a closed walkway at once.

## Setup

1. PHP 8.3 or newer, Composer, `curl.exe` (part of Windows) and Blender 4.2 or newer at `C:\Program Files\Blender Foundation\Blender 5.3\blender.exe` (if missing: `winget install --id BlenderFoundation.Blender`).
2. In PowerShell, in the project folder, build the two-floor test mall with every kind of marker. This is the Blender pipeline's own test; it keeps its files (about 15 seconds):

```powershell
$blender = "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"
Remove-Item -Recurse -Force tools/blender/work/manual42 -ErrorAction SilentlyContinue
& $blender -b --factory-startup --python-exit-code 1 -P tests/Blender/test_ism_map.py -- --keep tools/blender/work/manual42
```

**Expect:** the last lines say `66 passed, 0 failed`. The folder `tools/blender/work/manual42/package` holds `map_package.json` and, under `floors`, a plan, preview and model for floors `G` and `1`.

3. Prepare the map service (in the project folder):

```powershell
cd map-service
composer install
if (-not (Test-Path .env)) { copy .env.example .env; php artisan key:generate }
php artisan migrate:fresh --seed --force
```

`migrate:fresh` **deletes the map service's own data** (not the main app's).

4. Start it in a **second** PowerShell window, in `map-service`, and keep it open: `php artisan serve --host=127.0.0.1 --port=8100`

Account (map service only): `admin@map.test`, password `password`, sign in at <http://127.0.0.1:8100/login>.

### Create the data this file needs

In the first window (still in `map-service`):

```powershell
php artisan map:import ../tools/blender/work/manual42/package --name="Test mall" --mall=niagara-city-mall --publish
php artisan api-key:create "Guide 42 reader" --ability=maps:read
php artisan api-key:create "Guide 42 desk" --ability=maps:read --ability=graph:write
```

**Expect:** `Imported “Test mall” as <id> (published).` and `Floors: G, 1 · graph: 38 nodes, 77 edges · ...`. Each key command prints a key starting `ism_`. Keep them:

```powershell
$read = "ism_..."   # the "Guide 42 reader" key
$desk = "ism_..."   # the "Guide 42 desk" key
$api = "http://127.0.0.1:8100/api/v1"
```

## Steps

### 1. The graph is stored with the map

1. Sign in at <http://127.0.0.1:8100/login> as `admin@map.test` / `password` and open <http://127.0.0.1:8100/admin/maps>.

**Expect:** "Test mall" is **published**, its mall box shows `niagara-city-mall`, and the contents say `floors G, 1 · 38 nodes, 77 edges`.

2. Copy the map's id (the code under its name) into PowerShell: `$map = "<id>"`.

### 2. A broken graph is refused

1. Copy the package and break one walkway:

```powershell
Copy-Item -Recurse ../tools/blender/work/manual42/package ../tools/blender/work/manual42/broken
$file = "../tools/blender/work/manual42/broken/map_package.json"
$p = Get-Content $file -Raw | ConvertFrom-Json
$p.edges[0].to_node_code = "G-N9999"
$p | ConvertTo-Json -Depth 20 | Set-Content -Encoding utf8 $file
php artisan map:import ../tools/blender/work/manual42/broken --name="Broken" --mall=niagara-city-mall
```

**Expect:** an error starting `The map graph is invalid:` naming the edge that `joins an unknown node`. <http://127.0.0.1:8100/admin/maps> still lists only "Test mall".

### 3. QR codes

1. Open <http://127.0.0.1:8100/admin/checkpoints> (menu **QR codes**).

**Expect:** under "Mall niagara-city-mall" four codes `1-C01`, `1-C02`, `G-C01`, `G-C02`, each **active** and "on the published map", with its place (for example `G-C01` "Pillar P3 north row, south face"), a QR picture and an address like `http://127.0.0.1:8000/malls/niagara-city-mall/map/qr/<12 letters and digits>`.

2. Copy the slugs (the last part of the address) of `G-C01` and `1-C01`:

```powershell
$g = "<slug of G-C01>"
$one = "<slug of 1-C01>"
```

3. Scan by API: `curl.exe -s -H "Authorization: Bearer $read" "$api/checkpoints/$g"`

**Expect:** `checkpoint_code` `G-C01`, `mall_ref` `niagara-city-mall`, `floor_code` `G`, a `node` with its position, the `qr` pose and `"start":"checkpoint:<slug>"`.

4. Publish a new version of the same map: `php artisan map:import ../tools/blender/work/manual42/package --name="Test mall v2" --mall=niagara-city-mall --publish`, then reload <http://127.0.0.1:8100/admin/maps> and <http://127.0.0.1:8100/admin/checkpoints>.

**Expect:** "Test mall v2" is published and "Test mall" went back to **draft** by itself. The QR codes page still shows four codes with the **same** addresses as before. Set `$map` to the id of "Test mall v2".

### 4. Routes

1. Fastest route upstairs: `curl.exe -s -H "Authorization: Bearer $read" "$api/malls/niagara-city-mall/routes?from=checkpoint:$g&to=unit:1-105"`

**Expect:** `total` about 72 m, 67 s, 2 minutes; `floors` `["G","1"]`; the steps read: Start at Pillar P3 north row, south face. Walk 28 m. **Take East Escalator Up to First floor.** Walk 34 m. Turn left and walk 3 m. Arrive at Shop 1-105.

2. Step-free: add `&step_free=1` to the same address.

**Expect:** about 46 m and 69 s; the steps say **Take Central Lift up to First floor** and never the escalator.

3. Down from the first floor: `curl.exe -s -H "Authorization: Bearer $read" "$api/malls/niagara-city-mall/routes?from=checkpoint:$one&to=unit:G-101"`

**Expect:** **Take Central Lift down to Ground floor**: the escalator only goes up, so it is never used to go down.

4. Nearest toilet and two stops: `curl.exe -s -H "Authorization: Bearer $read" "$api/malls/niagara-city-mall/routes?from=checkpoint:$g&to[]=unit:G-101&to[]=facility-type:toilet"`

**Expect:** two `legs`; the steps contain **Stop at Shop G-101.** and end with **Arrive at the nearest toilet.**

5. Places: `curl.exe -s -H "Authorization: Bearer $read" "$api/malls/niagara-city-mall"`

**Expect:** the published map with `places`: floors G and 1, units `1-105`, `G-101`, `G-201`, the facility "Toilets G West", the type `toilet`, and the four QR codes with their `checkpoint:` places.

### 5. Closures

1. Open <http://127.0.0.1:8100/admin/maps>, click **Closures** on "Test mall v2".

**Expect:** "Nothing is closed."

2. From node `G-X-East-Escalator-Up`, to node `1-X-East-Escalator-Up`, reason `Repairs`, untick **Both directions**, click **Close**.

**Expect:** "One direction between G-X-East-Escalator-Up and 1-X-East-Escalator-Up closed; routes now go around." The table lists it with "Repairs".

3. Repeat the fastest route of step 4.1.

**Expect:** now about 46 m and 69 s **by the Central Lift**; no escalator step.

4. Close the lift through the API with the desk key:

```powershell
curl.exe -s -X PATCH -H "Authorization: Bearer $desk" -H "Content-Type: application/json" -d '{\"from\":\"G-X-Central-Lift\",\"to\":\"1-X-Central-Lift\",\"status\":\"closed\",\"reason\":\"Lift maintenance\"}' "$api/maps/$map/edges"
curl.exe -s -H "Authorization: Bearer $read" "$api/malls/niagara-city-mall/routes?from=checkpoint:$g&to=unit:1-105&step_free=1"
```

**Expect:** the first answer lists two edges (both directions) with `"reason":"Lift maintenance"`. The step-free route answers **422** with `"reason":"unreachable"` and "There is no open step-free route from Pillar P3 north row, south face to Shop 1-105."

5. On the Closures page click **Reopen** on each row.

**Expect:** "Nothing is closed." and the route of step 4.1 takes the escalator again.

### 6. Retiring and re-addressing a QR code

1. On <http://127.0.0.1:8100/admin/checkpoints> click **Retire** on `G-C01`, then run `curl.exe -s -w " %{http_code}" -H "Authorization: Bearer $read" "$api/checkpoints/$g"`.

**Expect:** the badge says **retired**; the API answers **410** with `"reason":"retired"`.

2. Click **Reactivate**, then **New address** and confirm.

**Expect:** the code is active with a different address. The old `$g` now answers **404** `"reason":"unknown"`; the new slug answers 200.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| no key | `curl.exe -s -w " %{http_code}" "$api/malls/niagara-city-mall/routes?from=checkpoint:$one&to=unit:G-101"` | 401 "Send an API key" |
| reader key | the closure `PATCH` of step 5.4 with `$read` instead of `$desk` | 403 "needs graph:write"; nothing closed |
| reader key | `to=G-101` (without `unit:`) | 422 `"reason":"unknown_place"`, "Write to as kind:value" |
| reader key | `to=unit:Z-999` | 422 `"reason":"unknown_place"`, "unit:Z-999 is not on this map." |
| reader key | `cost=cheapest` | 422, error on `cost` |
| reader key | a route on mall `no-such-mall` | 404 "This mall has no published map." |
| reader key | `"$api/maps/<id of Test mall>/routes?from=node:G-C01&to=unit:G-101"` (the draft) | 404 |
| desk key | close `G-C01` to `E-1-105` (two nodes not joined by a walkway) | 422 "No walkway joins G-C01 and E-1-105." |
| signed out | <http://127.0.0.1:8100/admin/checkpoints> and <http://127.0.0.1:8100/admin/maps/{id}/closures> | sent to the sign-in page |
| admin | set the mall of a map to `Not A Slug` on the Maps page | "The mall must be the slug of a Smart Mall app mall: lower-case letters, digits and dashes."; the mall is unchanged |

## If something looks wrong

- No QR codes: the map has no mall or is not published; set the mall on the Maps page and publish.
- Your own maps from before this change have no graph: run `php artisan migrate` and then `php artisan map:rebuild-graph` in `map-service`.
- 429 Too Many Requests: wait one minute (120 requests per minute per key).
- Times differ by a second: the Blender test scan is rebuilt each time; distances and the connector taken must match.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
