# 38 Map service: 3D signs and advertisements, API keys, main app connection

**Covers:** EPIC-05 Maps & Navigation, Jira ISM-57 / ISM-63, the separate app `map-service/` and `app/Modules/MapsNavigation` (decision D-019)
**Automated tests:** `map-service/tests/Feature/*` (33 tests), `map-service/tests/js/annotations.test.mjs` (8), `tests/Feature/Modules/MapsNavigation/MapServiceGatewayTest.php` (5)
**Needs:** nothing else; this file is self-contained
**Time:** about 30 minutes

## What you are proving

An administrator imports a map into the separate map service, places a shop sign and an advertisement in 3D, publishes the map and creates an API key. The Smart Mall app then reads the map and the sign with that key. Requests without a key, with a revoked key or with the wrong permission are refused.

## Setup

1. PHP 8.3 or newer and Composer. Blender 4.2 or newer at `C:\Program Files\Blender Foundation\Blender 5.3\blender.exe` (if missing: `winget install --id BlenderFoundation.Blender`).
2. A browser with WebGL: Chrome or Edge.
3. In PowerShell, in the project folder, build a small test map package from the synthetic scan:

```powershell
$blender = "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"
$work = "tools/blender/work/manual38"
Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
& $blender -b --factory-startup -P tools/blender/ism_map.py -- make-test-scan --out $work
& $blender -b --factory-startup -P tools/blender/ism_map.py -- prepare --blend $work/mall.blend --scan $work/test_scan_G.glb --floor G --elevation 0 --name "Ground floor"
& $blender -b --factory-startup -P tools/blender/ism_map.py -- draft-graph --blend $work/mall.blend --floor G
& $blender -b --factory-startup -P tools/blender/ism_map.py -- export --blend $work/mall.blend --out $work/package
```

**Expect:** the last line says `package written to ...manual38\package`.

4. Start the map service in a **second** PowerShell window:

```powershell
cd map-service
composer install
if (-not (Test-Path .env)) { copy .env.example .env; php artisan key:generate }
php artisan migrate:fresh --seed --force
php artisan serve --host=127.0.0.1 --port=8100
```

`migrate:fresh` **deletes the map service's own data** (not the main app's). Keep this window open.

Account (map service only): `admin@map.test`, password `password`, sign in at <http://127.0.0.1:8100/login>.

## Steps

### 1. Sign in

1. Open <http://127.0.0.1:8100/admin/maps> while signed out.

**Expect:** you are sent to <http://127.0.0.1:8100/login>.

2. Sign in as `admin@map.test` with password `wrong`.

**Expect:** "Wrong email or password." and you stay on the sign-in page.

3. Sign in with password `password`.

**Expect:** the **Maps** page, with "Import a map package" listing `manual38/package` (and any other exported packages).

### 2. Import the map

1. Choose `manual38/package`, name `Test corridor`, leave "Publish to the API now" unticked, click **Import**.

**Expect:** "Imported “Test corridor”." The table shows it as **draft**: floors G, 13 nodes, 0 signs and advertisements.

### 3. Place a shop sign and an advertisement

1. Click **Open 3D editor** for Test corridor.

**Expect:** the address becomes <http://127.0.0.1:8100/viewer/index.html?map=...>. A grey 3D corridor with pillars and a red navigation line appears. On the left: "← Back to maps", "Every change is saved to the map service automatically".

2. Click **＋ Shop sign**, then click a pillar face or a corridor wall.

**Expect:** a dark blue sign "Shop name" lies flat on the wall with a yellow outline. The "Selected" panel opens.

3. Type `Cafe Aroma` in **Text**, `Coffee · Bakery` in **Second line**, `G-101` in **Shop unit code**.

**Expect:** the sign updates as you type. Under the list: "Saved 1 item(s) to the map service at ...".

4. Click **＋ Advertisement**, then click the corridor floor.

**Expect:** a white poster on a stand, facing you. Change **Text** to `Winter Sale`, **Background** to red. Move **Facing (yaw)** and **Width**; the poster turns and resizes.

5. Click **Image…** and choose any small PNG or JPEG.

**Expect:** the picture fills the top of the poster; the message says "Saved 2 item(s)".

6. Press F5 to reload the page.

**Expect:** both items come back exactly as you left them, picture included.

7. Select the advertisement in the list and press **Delete** on the keyboard.

**Expect:** it disappears and the message says "Saved 1 item(s)". There is no undo: add it again with ＋ Advertisement so the next steps have two items.

### 4. Publish and create a key

1. Click **← Back to maps**, then **Publish** on Test corridor.

**Expect:** "“Test corridor” is now visible through the API." The status is **published**.

2. Open **API keys**. Name `Smart Mall app`, leave only `maps:read` ticked, empty expiry, click **Create key**.

**Expect:** a box "Your new key" showing `ism_` plus 40 characters. Copy it into Notepad.

3. Reload the page.

**Expect:** the key is **not** shown again; the table lists "Smart Mall app", `ism_` plus 8 more characters and …, **active**, `maps:read`.

### 5. Call the API

In a third PowerShell window (replace the key):

```powershell
$key = "ism_PASTE_YOUR_KEY"
curl.exe -s http://127.0.0.1:8100/api/v1/maps
curl.exe -s -H "Authorization: Bearer $key" http://127.0.0.1:8100/api/v1/maps
```

**Expect:** the first prints `{"message":"Send an API key: ..."}`. The second prints `{"data":[{"id":"...","name":"Test corridor",...,"annotations_count":2}]}`. Copy the `id`.

```powershell
$map = "PASTE_THE_ID"
curl.exe -s -H "Authorization: Bearer $key" "http://127.0.0.1:8100/api/v1/maps/$map/annotations?unit_code=G-101"
curl.exe -s -H "Authorization: Bearer $key" http://127.0.0.1:8100/api/v1/maps/$map
```

**Expect:** the first lists only Cafe Aroma with `"mount":"wall"`, `position` and `yaw_deg`. The second prints the whole map package, with a `file_urls` entry for `floors/G_model.glb` that contains `signature=`. Open that link in the browser: a GLB file downloads, without any key.

### 6. Connect the Smart Mall app

1. In the project's main `.env` (the Smart Mall app, not `map-service/.env`) add:

```
MAP_SERVICE_URL=http://127.0.0.1:8100
MAP_SERVICE_KEY=ism_PASTE_YOUR_KEY
```

2. In the project folder run `php artisan maps:service-check`.

**Expect:** "Connected. Published maps:" and a table row: Test corridor, floors G, 13 nodes, 0 QR codes, 2 signs and ads.

3. On the map service, Maps page, click **Unpublish** on Test corridor, then run `php artisan maps:service-check` again.

**Expect:** "Connected, but the map service has no published maps yet." Publish it again.

### 7. Revoke the key

1. On **API keys**, click **Revoke** for Smart Mall app and confirm.

**Expect:** "Key “Smart Mall app” revoked. ..." and the badge **revoked**.

2. Run `php artisan maps:service-check` in the project folder.

**Expect:** a red "The map service refused the API key (unknown, revoked or expired). Create a new key on its API keys page." and the command fails.

## Things that must be refused

| Account or key | Try | Expect |
|---|---|---|
| signed out | <http://127.0.0.1:8100/admin/api-keys> | sent to the sign-in page |
| no key | `curl.exe -s http://127.0.0.1:8100/api/v1/maps` | 401 "Send an API key" |
| made-up key | `curl.exe -s -H "Authorization: Bearer ism_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" http://127.0.0.1:8100/api/v1/maps` | 401 "unknown, revoked or expired" |
| `maps:read` key | `curl.exe -s -X POST -H "Authorization: Bearer $key" -H "Accept: application/json" http://127.0.0.1:8100/api/v1/maps/$map/annotations` | 403 "needs annotations:write", nothing created |
| `maps:read` key, map unpublished | `curl.exe -s -H "Authorization: Bearer $key" http://127.0.0.1:8100/api/v1/maps/$map` | 404 "Map not found." |
| anyone | a `file_urls` link with one character of `signature=` changed | 403, no file |
| revoked key | any API call | 401 |
| admin | in the 3D editor, clear **Text** completely | the sign keeps its last text (an empty text is never saved) |

## If something looks wrong

- "Sign in to the map service first" in the 3D editor: your session ended. Sign in again at <http://127.0.0.1:8100/login> and reopen the map from Maps.
- Black or empty 3D view: the browser blocks WebGL, or there is no internet (three.js loads from cdn.jsdelivr.net). Try Chrome or Edge.
- The import list is empty: the package must be under `tools/blender/work/` and contain `map_package.json` (Setup step 3).
- `maps:service-check` says "not reachable": the map service window (Setup step 4) is not running on port 8100.
- `429 Too Many Requests`: wait one minute (120 requests per minute per key).

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
