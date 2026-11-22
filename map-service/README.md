# ISM Map Service

A separate small Laravel 13 app that holds the indoor maps made with the [Blender pipeline](../tools/blender/README.md). Administrators place **shop signs and advertisements in 3D** on them. Other apps, first of all the Smart Mall app, read everything through a versioned API **with an API key** (decision D-019).

```
tools/blender export  ->  map-service (import, 3D editor, API keys)  ->  /api/v1  ->  Smart Mall app (MAP_SERVICE_KEY)
```

It has its own database (SQLite by default) and its own users, and shares no code or tables with the main app.

## Run it

From this folder (`map-service/`), PHP 8.3 or newer:

```powershell
composer install
copy .env.example .env          # first time only
php artisan key:generate        # first time only
php artisan migrate --seed      # creates database/database.sqlite and admin@map.test / password
php artisan serve --host=127.0.0.1 --port=8100
```

Open <http://127.0.0.1:8100> and sign in as `admin@map.test` with password `password`. To add another administrator, run `php artisan map:admin you@example.com`.

## Use it

1. **Import a map.** Export a package with the Blender pipeline into `tools/blender/work/<name>/package`. On **Maps** it appears in the import list; pick it, give it a name, and tick "Publish" if apps may read it now. From the command line: `php artisan map:import ../tools/blender/work/sample/package --name="Sample floor" --publish`. An `annotations.json` saved by the viewer next to `map_package.json` is imported too.
2. **Place signs and advertisements.** Click **Open 3D editor**. Choose "＋ Shop sign" or "＋ Advertisement", then click a wall (it lies flat on the wall) or the floor (it stands up on a pole or stand, facing you). Edit the text, second line, shop unit code, colours, width, height, facing and an optional picture. Every change is saved automatically.
3. **Publish** the map so the API shows it, or **Unpublish** to hide it again.
4. **Create an API key** on **API keys**: give it a name and its abilities. The key is shown **once**; put it in the other app's `.env`. **Revoke** a key to cut an app off at once. From the command line: `php artisan api-key:create "Smart Mall app" --ability=maps:read`.

The 3D viewer also works without signing in, as a standalone file viewer: open <http://127.0.0.1:8100/viewer/index.html>, then "Open package folder…". In that mode changes are kept in the browser and saved as an `annotations.json` file.

## API v1

Every request needs a key: `Authorization: Bearer ism_...` (or `X-Api-Key: ism_...`). Only published maps are visible. Each key may make 120 requests per minute (`MAP_API_PER_MINUTE`).

| Method and path | Ability | Returns |
|---|---|---|
| `GET /api/v1/maps` | `maps:read` | `{data: [{id, name, floor_codes, stats, annotations_count, package_generated_at, ...}]}` |
| `GET /api/v1/maps/{id}` | `maps:read` | The whole `map_package.json` (floors, nodes, edges, connectors, checkpoints, shop entrances, facilities), plus `file_urls` (each floor's plan, preview and GLB), `urls_expire_at` and `map` |
| `GET /api/v1/maps/{id}/annotations?floor=G&unit_code=G-101&kind=advertisement` | `maps:read` | `{map_id, data: [annotation]}` |
| `GET /api/v1/maps/{id}/annotations/{code}` | `maps:read` | `{data: annotation}` |
| `POST /api/v1/maps/{id}/annotations` | `annotations:write` | 201 `{data: annotation}` with the next code (`ann-0001`, ...) |
| `PUT /api/v1/maps/{id}/annotations/{code}` | `annotations:write` | `{data: annotation}` |
| `DELETE /api/v1/maps/{id}/annotations/{code}` | `annotations:write` | 204 |

An **annotation** has the same shape as the viewer's `annotations.json`:

```json
{
  "id": "ann-0001", "kind": "shop_sign", "floor_code": "G", "unit_code": "G-101",
  "text": "Cafe Aroma", "subtitle": "Coffee and bakery", "background": "#1f2a44", "color": "#ffffff",
  "image": null, "width_m": 2.4, "lift_m": 0, "mount": "wall",
  "position": {"x": 3.81, "y": 6.38, "z": 1.48}, "yaw_deg": 243
}
```

- `kind`: `shop_sign` (4:1 panel) or `advertisement` (2:3 poster).
- `mount`: `wall` (the panel centre sits at `position`, raised by `lift_m`) or `floor` (the panel's bottom edge is `lift_m` above `position`, with a pole).
- `position` and `yaw_deg` are in the map's frame: metres, x and y horizontal, z up, yaw counterclockwise from +x. That is the same frame as `map_package.json`; three.js and WebXR use `(x, z, -y)`.
- `image` on writing: a PNG, JPEG or WebP **data URL** under 1 MB (stored as a file), `null` to remove it, or the link you were given to keep the current picture. On reading it is a signed link.

**Errors:** 401 means no key, or a key that is unknown, revoked or expired. 403 means the key lacks the ability. 404 means the map is unknown or not published, or the annotation is unknown. 422 means validation failed (`errors` per field). 429 means too many requests.

**Files:** floor models, plan images and annotation pictures are served from `/files/maps/...` behind **temporary signed links** (valid 120 minutes, `MAP_SIGNED_URL_MINUTES`). A browser, or the WebXR page of the main app, loads them directly; the API key never goes to a browser. The links allow any origin (CORS `*`), because the signature itself is the permission.

Example:

```powershell
curl.exe -H "Authorization: Bearer $env:MAP_SERVICE_KEY" http://127.0.0.1:8100/api/v1/maps
```

The main app's client is `App\Modules\MapsNavigation\Contracts\MapServiceGateway`. Check the connection there with `php artisan maps:service-check`.

## Security notes

- Keys are `ism_` plus 40 random characters; only their SHA-256 hash is stored, with a 12-character prefix for recognising them. Lost keys cannot be shown again: create a new one and revoke the old.
- The key is checked before anything else, so a bad key learns nothing about which maps exist.
- Importing from the browser is limited to folders under `MAP_IMPORT_ROOT` (default `../tools/blender/work`). Package file names must stay inside the package folder.
- Uploaded pictures are checked to be real PNG, JPEG or WebP data under 1 MB.

## Tests

```powershell
php artisan test --compact          # 33 PHP tests: keys, API, files, import, admin
node --test tests/js/annotations.test.mjs   # 8 tests of the viewer's annotation logic (Node 20+, no packages)
```

The manual check is [guide/TestByYourself/38_map_service_and_api_keys.md](../guide/TestByYourself/38_map_service_and_api_keys.md).
