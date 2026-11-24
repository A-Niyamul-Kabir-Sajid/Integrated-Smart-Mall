# ISM Map Service

A separate small Laravel 13 app that holds the indoor maps made with the [Blender pipeline](../tools/blender/README.md). Administrators place **shop signs and advertisements in 3D** on them. It owns each map's **navigation graph**, the mall's **QR codes** and the **route engine**, and serves an **embeddable map window**. Other apps, first of all the Smart Mall app, use everything through a versioned API **with an API key** (decisions D-020 and D-021). The full API documentation is public at <http://127.0.0.1:8100/docs>.

```
tools/blender export  ->  map-service (import, graph, QR codes, routes, 3D editor, API keys)  ->  /api/v1  ->  Smart Mall app (MAP_SERVICE_KEY)
                                                                              /embed  ->  iframe in the Smart Mall app's Map page
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

1. **Import a map.** Export a package with the Blender pipeline into `tools/blender/work/<name>/package`. On **Maps** it appears in the import list; pick it, give it a name, the **mall** (the Smart Mall app's mall slug, for example `niagara-city-mall`) and tick "Publish" if apps may read it now. From the command line: `php artisan map:import ../tools/blender/work/sample/package --name="Sample floor" --mall=niagara-city-mall --publish`. The navigation graph is checked and stored; a broken graph is refused with the reasons. An `annotations.json` saved by the viewer next to `map_package.json` is imported too. Maps imported before the graph tables existed: `php artisan map:rebuild-graph`.
2. **Place signs and advertisements.** Click **Open 3D editor**. Choose "＋ Shop sign" or "＋ Advertisement", then click a wall (it lies flat on the wall) or the floor (it stands up on a pole or stand, facing you). Edit the text, second line, shop unit code, colours, width, height, facing and an optional picture. Every change is saved automatically.
3. **Publish** the map so the API shows it, or **Unpublish** to hide it again. A mall has one published map: publishing a new one sends the old one back to draft. **Set mall** links or moves a map to a mall.
4. **QR codes.** Publishing a map of a mall creates its QR checkpoints on **QR codes**, each with a random address (`MAP_QR_URL`, a page of the Smart Mall app) and a printable code. Addresses stay the same for new map versions. **Retire** a code that was taken down, **New address** when a printed code must stop working.
5. **End points.** On a map's **End points** page (or in the 3D editor with **＋ End point** and a click on the floor) add a destination such as a kiosk or meeting place. It is joined to the nearest walkway of its floor (at most 6 m) and gets a **key** like `k7m2x9q4ab`; apps route to it with `point:<key>`, and the key stays the same for new map versions of the mall. Delete removes exactly what it added.
6. **Closures.** On a map's **Closures** page, close a walkway, lift or escalator for a while (by its two node codes); routes go around it at once. **Reopen** it afterwards.
7. **Create an API key** on **API keys**: its name, abilities and the sites allowed to show the embedded map (origins such as `http://127.0.0.1:8000`). The key is shown **once**; put it in the other app's `.env`. **Revoke** a key to cut an app off at once, including its open embedded maps. From the command line: `php artisan api-key:create "Smart Mall app" --ability=maps:read --origin=http://127.0.0.1:8000`.

The 3D viewer also works without signing in, as a standalone file viewer: open <http://127.0.0.1:8100/viewer/index.html>, then "Open package folder…". In that mode changes are kept in the browser and saved as an `annotations.json` file.

## API v1

Every request needs a key: `Authorization: Bearer ism_...` (or `X-Api-Key: ism_...`). Only published maps are visible. Each key may make 120 requests per minute (`MAP_API_PER_MINUTE`). The complete reference with examples, places, route steps, QR refusals, the embedded map and its `postMessage` events is the public page **/docs** (<http://127.0.0.1:8100/docs>).

| Method and path | Ability | Returns |
|---|---|---|
| `GET /api/v1/malls/{mall}` | `maps:read` | The mall's published map and its `places` (floors, units, facilities, facility types, QR codes), each with its `kind:value` place |
| `GET\|POST /api/v1/malls/{mall}/routes` | `maps:read` | A route: `from`, `to` (one place or up to 10 in order), `step_free`, `cost` `time` or `distance`; answers `total`, `floors`, `path`, `legs` and `steps` |
| `GET /api/v1/checkpoints/{slug}` | `maps:read` | Where a scanned QR code puts the visitor, with `start` for routes; 404 unknown, 410 retired, 409 not on the current map |
| `POST /api/v1/embed-links` | `maps:read` | 201 `{data: {url, expires_at, map_id, mall_ref}}`: a signed link to the embedded map for an iframe; 422 `no_origins` when the key has no allowed sites |
| `GET /api/v1/maps/{id}/places`, `GET\|POST /api/v1/maps/{id}/routes`, `GET /api/v1/maps/{id}/closures` | `maps:read` | The same for one map by id, and its closed walkways |
| `PATCH /api/v1/maps/{id}/edges` | `graph:write` | Close or reopen a walkway: `from`, `to` (node codes), `status`, `reason`, `both_ways` |
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
- The embedded map opens only from a signed link (15 minutes, `MAP_EMBED_LINK_MINUTES`), may be framed only by the key's allowed origins (`Content-Security-Policy: frame-ancestors`) and works with an encrypted session token (240 minutes, `MAP_EMBED_SESSION_MINUTES`) naming the map and key, never the key itself. Revoking the key or unpublishing the map ends it.
- A QR code holds only a random slug, never coordinates or a map version.

## Tests

```powershell
php artisan test --compact          # 115 PHP tests: keys, API, files, import, admin, graph, QR codes, routes, closures, end points, embed, docs
node --test tests/js/annotations.test.mjs   # 8 tests of the viewer's annotation logic (Node 20+, no packages)
```

Manual checks: [guide 41](../guide/TestByYourself/41_map_service_and_api_keys.md) (import, 3D signs, keys), [guide 42](../guide/TestByYourself/42_map_graph_checkpoints_and_routes.md) (graph, QR codes, routes, closures) and [guide 43](../guide/TestByYourself/43_mall_map_embed_and_docs.md) (the embedded map in the Smart Mall app, `/docs`) and [guide 45](../guide/TestByYourself/45_map_end_points.md) (end points and their keys).
