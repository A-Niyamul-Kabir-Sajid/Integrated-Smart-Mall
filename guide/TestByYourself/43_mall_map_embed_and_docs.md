# 43 The mall map in the Smart Mall app: embedded map, QR landing page, API docs

**Covers:** EPIC-05 Maps & Navigation, Jira ISM-64 (with ISM-61 / ISM-62), `app/Modules/MapsNavigation` and the separate app `map-service/` (decision D-021)
**Automated tests:** `tests/Feature/Modules/MapsNavigation/MallMapPageTest.php`, `MapServiceGatewayTest.php`, `map-service/tests/Feature/EmbedTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 30 minutes

## What you are proving

A visitor opens the mall's **Map** page, or scans a QR code, and sees the map service's map inside the Smart Mall app, with a route from where they stand to a shop or facility, on any floor, in English or Bangla. The API key never reaches the browser. The embedded map can be shown only on the allowed site, an old or altered link is refused, and revoking the key ends it. The API documentation is public at `/docs`.

## Setup

1. PHP 8.3 or newer, Composer, Node, `curl.exe`, Chrome or Edge, and Blender 4.2 or newer at `C:\Program Files\Blender Foundation\Blender 5.3\blender.exe` (if missing: `winget install --id BlenderFoundation.Blender`).
2. In PowerShell, in the project folder, build the two-floor test mall with QR codes, shops, a lift and an escalator (the Blender pipeline's own test, about 15 seconds):

```powershell
$blender = "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"
Remove-Item -Recurse -Force tools/blender/work/manual43 -ErrorAction SilentlyContinue
& $blender -b --factory-startup --python-exit-code 1 -P tests/Blender/test_ism_map.py -- --keep tools/blender/work/manual43
```

**Expect:** `66 passed, 0 failed`.

3. Map service, in the project folder (`migrate:fresh` **deletes the map service's own data**):

```powershell
cd map-service
composer install
if (-not (Test-Path .env)) { copy .env.example .env; php artisan key:generate }
php artisan migrate:fresh --seed --force
php artisan map:import ../tools/blender/work/manual43/package --name="Test mall" --mall=niagara-city-mall --publish
php artisan api-key:create "Smart Mall app" --ability=maps:read --origin=http://127.0.0.1:8000
cd ..
```

**Expect:** `Imported “Test mall” ... (published)` and `Embedded map allowed on: http://127.0.0.1:8000`, then a key starting `ism_`.

4. Smart Mall app, in the project folder (`migrate:fresh` **deletes local data**): in `.env` set `MAP_SERVICE_URL=http://127.0.0.1:8100` and `MAP_SERVICE_KEY=<the ism_ key>`, then:

```powershell
php artisan config:clear
php artisan migrate:fresh --seed
npm run build
```

5. Start both, each in its own PowerShell window, and keep them open:
   - in `map-service`: `php artisan serve --host=127.0.0.1 --port=8100`
   - in the project folder: `php artisan serve` (<http://127.0.0.1:8000>)

6. Check the connection: `php artisan maps:service-check`. **Expect:** "Connected. Published maps:" and "Test mall".

Accounts: none needed for visitors. Map service administrator (for step 6): `admin@map.test`, password `password`, at <http://127.0.0.1:8100/login>.

### Create the data this file needs

Sign in at <http://127.0.0.1:8100/login> as `admin@map.test` and open <http://127.0.0.1:8100/admin/checkpoints>. Note the addresses of `G-C01` ("Pillar P3 north row, south face") and `1-C01` ("Side corridor, west wall by the lift"); each looks like `http://127.0.0.1:8000/malls/niagara-city-mall/map/qr/<12 letters and digits>`.

## Steps

### 1. The Map page

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall> signed out.

**Expect:** the mall menu shows **Map** between Products and Facilities.

2. Click **Map** (<http://127.0.0.1:8000/malls/niagara-city-mall/map>).

**Expect:** "Scan the QR code nearest to you to start from where you are, or choose your start below." and, in a frame, the map: **From** "Where are you?", **To** "Where to?", tabs **Ground floor** and **First floor**, the plan of the ground floor with grey walkways, green shop dots (G-101, G-201), the orange "Toilets G West" and blue "QR" dots, and zoom buttons. The steps panel says "Choose where you are and where to go."

3. Right-click the map frame, **View frame source** (or open the page source with Ctrl+U) and search for `ism_`.

**Expect:** not found. The frame address starts `http://127.0.0.1:8100/embed/maps/` and ends with `signature=...`.

### 2. A route across floors

1. In the frame choose From "Pillar P3 north row, south face (G)", To "Shop 1-105", click **Show route**.

**Expect:** above the frame on the right and in the frame: "2 min · 72 m"; in the frame "Ground floor → First floor". A blue line runs from the green "You are here" dot to the escalator, with a purple **↑ First floor** label. Both floor tabs have a dot. Steps: Start at Pillar P3 north row, south face. · Walk 28 m. · **Take East Escalator Up to First floor.** · Walk 34 m. · Turn left and walk 3 m. · Arrive at Shop 1-105.

2. Click **↑ First floor** (or the **First floor** tab).

**Expect:** the first-floor plan with the rest of the blue line ending at a red dot by shop 1-105.

3. Tick **Step-free** and click **Show route**.

**Expect:** about 46 m; the steps take **Central Lift up to First floor**, never the escalator.

4. Drag the map, use the mouse wheel and **+** / **−**, then **Fit**.

**Expect:** the plan moves and zooms; **Fit** shows the whole floor again.

### 3. Show on map from a shop

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops>, open a shop, find "Unit ..." under its location and click **Show on map**.

**Expect:** the Map page with **To** set to that unit. If the demo shop's unit is not on the test map, the page says "That place is not on the map. Choose it from the list." and the plain map still shows.

2. Open <http://127.0.0.1:8000/malls/niagara-city-mall/map?to=unit:G-101>.

**Expect:** **To** is "Shop G-101"; choose a **From** and click **Show route** to see the route.

### 4. Scanning a QR code

1. Open the address of `1-C01` from the setup (as a phone would after scanning).

**Expect:** "You are at: Side corridor, west wall by the lift". The frame opens on the **First floor** with From already "Side corridor, west wall by the lift (1)" and a green **You are here** dot at the code, before any destination is chosen.

2. Choose To "Nearest: Toilet", tick **Step-free**, click **Show route**.

**Expect:** "First floor → Ground floor"; steps include **Take Central Lift down to Ground floor** and end with **Arrive at the nearest toilet.**

### 5. Bangla

1. Click **বাং** in the top bar, then open <http://127.0.0.1:8000/malls/niagara-city-mall/map>.

**Expect:** the menu shows **ম্যাপ**; in the frame the labels are Bangla: **কোথা থেকে**, **কোথায়**, **সিঁড়ি ছাড়া**, **পথ দেখাও**. Route steps stay in English (a known limit). Click **EN** to switch back.

### 6. Closures reach the open map

1. Show the route of step 2.1 again and keep the page open.
2. In another tab sign in to the map service and open <http://127.0.0.1:8100/admin/maps>, click **Closures** on "Test mall", close `G-X-East-Escalator-Up` to `1-X-East-Escalator-Up` (untick **Both directions**), reason `Repairs`.
3. Go back to the map tab and wait up to one minute (or click **Show route**).

**Expect:** the summary starts "Route updated: a walkway changed." and the steps now take **Central Lift**. Reopen the escalator on the Closures page afterwards.

### 7. The API documentation

1. Open <http://127.0.0.1:8100/docs> signed out.

**Expect:** "ISM Map Service API" with Getting started, Keys and abilities, Endpoints, Places, Routes, QR checkpoints, Embedded map, Embed messages (`ism-map:ready`, `ism-map:route`, `ism-map:navigate` ...), Closures, Errors and limits, Coordinates. No key or key prefix appears.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| visitor | copy the frame address (step 1.3) and open it in a new tab after changing one letter of `signature` | 403 "Invalid signature" |
| visitor | open the same frame address after 16 minutes | 403 (links last 15 minutes; reload the Map page for a new one) |
| anyone | `curl.exe -s -D - -o NUL "<frame address>"` | the header `Content-Security-Policy: ... frame-ancestors http://127.0.0.1:8000`: other sites may not frame it |
| visitor | <http://127.0.0.1:8000/malls/niagara-city-mall/map?to=G-101> (without `unit:`) | the plain map with "That place is not on the map. Choose it from the list."; nothing is preselected |
| visitor | <http://127.0.0.1:8000/malls/niagara-city-mall/map/qr/aaaaaaaaaaaa> (unknown code) | 404 |
| visitor | a QR code after **Retire** on <http://127.0.0.1:8100/admin/checkpoints> | the Map page says "This QR code is no longer in use. Choose where you are on the map, or scan another code." with no start |
| map admin | on <http://127.0.0.1:8100/admin/api-keys> click **Revoke** on "Smart Mall app", then reload the open Map page, then open the Map page again | the open frame says "This map has expired. Reload the page."; the new page says "The map is not available right now. Please try again in a few minutes." (503) |
| map admin | unpublish "Test mall" on <http://127.0.0.1:8100/admin/maps>, open <http://127.0.0.1:8000/malls/niagara-city-mall/map> | 404 "This mall has no indoor map yet." |

## If something looks wrong

- "The map is not available right now": run `php artisan maps:service-check` in the project folder; it says what is wrong (service not running, key refused, not configured). After editing `.env` run `php artisan config:clear`.
- The frame stays blank with an error in the browser console about `frame-ancestors`: the key's allowed origins do not include the address in your browser bar (for example `localhost` instead of `127.0.0.1`). Add it on <http://127.0.0.1:8100/admin/api-keys> under "Embedded map allowed on".
- 429 Too Many Requests: wait one minute (30 map pages per minute per visitor).

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
