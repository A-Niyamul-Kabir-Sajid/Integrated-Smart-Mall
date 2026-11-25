# 46 AR directions on an Android phone

**Covers:** EPIC-05 Maps & Navigation, Jira ISM-65 (story ISM-63), the separate app `map-service/` (decision D-023) and the Smart Mall app's Map page (`app/Modules/MapsNavigation`)
**Automated tests:** `map-service/tests/Feature/ArDirectionsTest.php`, `map-service/tests/js/ar.test.mjs`, `tests/Feature/Modules/MapsNavigation/MallMapPageTest.php`
**Needs:** nothing else; this file is self-contained. Part B also needs an Android phone that supports ARCore, its USB cable, a printer and tape.
**Time:** part A about 15 minutes on a computer; part B about 40 minutes with the phone.

## What you are proving

A visitor who scans a QR code of the mall with an Android phone taps **View in AR** and lines the phone up with the printed code. They can point the camera at it or hold the phone against it. They then follow arrows on the floor to a shop. At the lift they are told to take it and line up again on the next floor. Walking away from the route offers a new route, and the page says when they arrive. Computers and iPhones never show the AR button, and the AR page says why AR is not possible there. Part B also records how well the AR view lines up, which conflict C-07 still needs.

## Setup

1. PHP 8.3 or newer, Composer, Node, `curl.exe`, Chrome or Edge, and Blender 4.2 or newer at `C:\Program Files\Blender Foundation\Blender 5.3\blender.exe` (if missing: `winget install --id BlenderFoundation.Blender`).
2. In PowerShell, in the project folder, build the two-floor test mall with QR codes, shops, a lift and an escalator (the Blender pipeline's own test, about 15 seconds):

```powershell
$blender = "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe"
Remove-Item -Recurse -Force tools/blender/work/manual46 -ErrorAction SilentlyContinue
& $blender -b --factory-startup --python-exit-code 1 -P tests/Blender/test_ism_map.py -- --keep tools/blender/work/manual46
```

**Expect:** `66 passed, 0 failed`.

3. Map service, in the project folder (`migrate:fresh` **deletes the map service's own data**):

```powershell
cd map-service
composer install
if (-not (Test-Path .env)) { copy .env.example .env; php artisan key:generate }
php artisan migrate:fresh --seed --force
php artisan map:import ../tools/blender/work/manual46/package --name="Test mall" --mall=niagara-city-mall --publish
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

Accounts: none for visitors. Map service administrator: `admin@map.test`, password `password`, at <http://127.0.0.1:8100/login>.

### Only for part B: the phone

7. On the phone:
   - It must support ARCore (Google's list: <https://developers.google.com/ar/devices>).
   - Install or update **Google Play Services for AR** from the Play Store, and update **Chrome**.
   - In **Settings → About phone**, tap **Build number** seven times, then turn on **Developer options → USB debugging**.
8. On the computer, install the Android platform tools (`winget install --id Google.PlatformTools`, then open a new PowerShell). Connect the phone by USB and allow debugging on the phone, then:

```powershell
adb devices
adb reverse tcp:8000 tcp:8000
adb reverse tcp:8100 tcp:8100
```

**Expect:** the phone listed as `device`; each `reverse` line prints its port or nothing. On the phone, `http://127.0.0.1:8000` now opens the Smart Mall app running on the computer, and `127.0.0.1` counts as a secure address, which AR needs. Run the two `reverse` lines again whenever the cable is reconnected.

### Create the data this file needs

1. Sign in at <http://127.0.0.1:8100/login> as `admin@map.test` and open <http://127.0.0.1:8100/admin/checkpoints>. Note the addresses of these two QR codes. Each looks like `http://127.0.0.1:8000/malls/niagara-city-mall/map/qr/<12 letters and digits>`:
   - **G-C02**: "Side corridor, west wall by the lift", floor G, 4 m from the lift.
   - **1-C02**: "Pillar P2 north row, south face", floor 1, 11 m from shop 1-105.
2. For part B only:
   - Click **Print signs at real size** and print at 100 % ("Actual size", not "Fit to page").
   - **Expect:** one code per page. Under each are its code, its label and "the black square is 18 cm wide. Hang it upright on floor … with its centre 1.40 m above the floor". Measure the black square with a ruler: 18 cm (± 2 mm).
   - Tape the **G-C02** sign upright on a wall with about 5 m of free floor in front of it and to the sides, its centre 1.40 m above the floor.
   - Tape the **1-C02** sign on another wall with about 11 m of free floor in front of it (a corridor is best).

The test mall is not your building, so its walls will not match your room. The arrows are drawn from the code you line up at, so judge them by their direction from the code and by the distances, not by your walls.

## Part A: on a computer

### A1. No AR button on a computer

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/map> in Chrome on the computer.
2. In the frame choose From "Side corridor, west wall by the lift (G)" (not the one ending "(1)"), To "Shop 1-105", click **Show route**.

**Expect:** "1 min · 32 m · Ground floor → First floor" and the steps "Walk 4 m. · Take Central Lift up to First floor. · …". There is **no** "View in AR" button: a computer cannot show AR.

### A2. The AR page and its preview

1. In PowerShell, in `map-service`: `php artisan tinker ..\guide\TestByYourself\scripts\ar-preview-link.php`.

**Expect:** `AR page (open within 15 minutes): http://127.0.0.1:8100/embed/maps/<id>/ar?expires=…&signature=…#preview`.

2. Open that address in Chrome on the computer.

**Expect:**
- "AR DIRECTIONS", **To Shop 1-105** and "32 m · 1 min from Side corridor, west wall by the lift".
- In red: "This phone cannot show AR here: it needs Chrome on Android with Google Play Services for AR. Use the map instead."
- No **Start AR** button, but a **Preview without a camera** button (because of `#preview`), the three steps of how it works, and **Back to the map**.

3. Click **Preview without a camera**.

**Expect:**
- A dark view at eye height with a grid and grey walkways, and blue arrows on a pale blue strip, pulsing in the walking direction.
- At the top: an arrow icon and "Walk 4 m." with "now · 32 m · 1 min left".
- A purple sign "⇧ Central Lift · First floor" ahead.
- After a few seconds: "Take Central Lift up to First floor." and the banner "Take Central Lift up to First floor. Then line up at a QR code on First floor." with **I am on First floor**.
- About 1.5 seconds later the preview goes to the first floor by itself, and the top shows the next step ("Walk 13 m.", then "Turn right and walk 7 m.", …).
- At the end: a red pin with "Shop 1-105" and "You have arrived at Shop 1-105.", about 30 seconds after you started.

4. Click **Details**.

**Expect:** text such as "Line-up: preview · QR G-C02", "Floor: not found", "Camera pictures: not available (hold the phone to the code)", "You: x …, y … on 1 · 0.… m from the route · … of 32 m", "Tracking: normal".

### A3. Off the route in the preview

1. Open a new link (repeat A2 step 1, open it, click **Preview without a camera**). After two seconds press and hold **←** for about one second (the walk stops following the route; the arrow keys now walk and turn), then hold **↑** for about four seconds.

**Expect:** about three seconds after you are more than 4 m from the route, the banner "You are off the route." with **New route from here**.

2. Click **New route from here**.

**Expect:** the banner goes; new arrows start near you and lead back to the route. The top shows the first step of the new route.

3. Press **P**. **Expect:** nothing moves while you press the arrow keys. Press **P** again to go on.

### A4. Bangla

1. In PowerShell, in `map-service`: `$env:LINK_LANG='bn'; php artisan tinker ..\guide\TestByYourself\scripts\ar-preview-link.php; $env:LINK_LANG=''`. Open the printed address.

**Expect:**
- "এআর দিকনির্দেশনা" and "গন্তব্য: Shop 1-105". Shop and place names come from the map and stay as they are.
- In red: "এই ফোনে এখানে এআর দেখানো যাচ্ছে না: …".
- The steps start "মেঝের উপর দিয়ে ফোনটি ধীরে ধীরে নাড়ান…".
- After **ক্যামেরা ছাড়া দেখুন**, route steps are in English (as on the map) and the rest is in Bangla, for example "বাকি 32 মিটার · 1 মিনিট".

### A5. The documentation

1. Open <http://127.0.0.1:8100/docs#ar>.

**Expect:** the section **AR directions**:
- the embedded map's **View in AR**;
- `xr-spatial-tracking` in the iframe's `allow` list;
- lining up by camera or by holding the phone to the code;
- printing at real size with **Print signs at real size**;
- the need for https or `127.0.0.1`.

Under **Embedded map** the iframe example reads `allow="fullscreen; xr-spatial-tracking"`.

## Part B: on the Android phone

Do this part standing by the printed G-C02 sign, with the phone connected and the `adb reverse` lines run (setup steps 7 and 8).

### B1. The AR button on the phone

1. On the phone, scan the printed **G-C02** sign with the camera app, or type its address (from "Create the data this file needs") into **Chrome**. If the camera app opens another browser, copy the address into Chrome.

**Expect:** the Smart Mall app's QR page "You are at: Side corridor, west wall by the lift" with the map, From already set.

2. In the frame choose To "Shop 1-105" and tap **Show route**.

**Expect:** the route ("1 min · 32 m") and, under **Show route**, the button **View in AR**.

3. Tap **View in AR**.

**Expect:** a new tab: "To Shop 1-105", "32 m · 1 min from Side corridor, west wall by the lift", "Ready. Stand by a QR code of the mall, then tap Start AR." and the blue **Start AR** button.

### B2. Find the floor

1. Tap **Start AR** and allow the camera when Chrome asks.

**Expect:** the camera picture fills the screen with the top box "To Shop 1-105" and the panel "Move the phone slowly over the floor until the ring appears."

2. Point the phone at the floor a metre or two in front of you and move it slowly from side to side.

**Expect:** a thin blue ring lies flat on the floor and follows where you point; then "Floor found." The panel changes to the line-up text. (If no ring appears after a while, **Skip** appears; skipping is allowed, the route height then comes from the code's height.)

### B3. Line up with the camera

1. Stand about half a metre from the G-C02 sign, a little to one side, and point the phone at it so the code fills about a third of the screen (the dashed square in the middle helps).

**Expect:** the panel counts "Hold still… 1 of 6" up to 6. Then the toast "Lined up with Side corridor, west wall by the lift. The blue frame should sit on the printed code." appears, and:
- a blue square frame lies on the printed code with the label "QR G-C02" under it;
- arrows on the floor start about 0.8 m in front of the code and pulse in the walking direction;
- the top box shows "Walk 4 m." and "now · 32 m · 1 min left" (numbers may differ by a metre).

Record in the table at the end how far the frame is from the printed code (cm). If the panel says "Come closer to the code (about half a metre).", come closer. If it never counts, tap **Details**: "Camera pictures: not available" means this phone's Chrome gives no camera pictures, so use B4.

### B4. Line up by holding the phone to the code

1. Tap **Re-align**. In the list choose "Side corridor, west wall by the lift (G-C02)" under **Ground floor**.
2. Hold the phone flat against the printed code, camera side to the code and the phone upright, and tap **Align**.

**Expect:** "Lined up with Side corridor, west wall by the lift. …". Step back: the blue frame sits on the code and the arrows are where they were after B3, within a few centimetres. Record the frame offset for this method too.

3. Lay the phone flat on a table, tap **Re-align**, then **Align**.

**Expect:** "Hold the phone upright, flat against the code." and nothing changes. Tap **Keep the current line-up** to go back.

### B5. The lift and the next floor

1. Walk along the arrows.

**Expect:**
- The top changes to "Take Central Lift up to First floor." with "in 3 m" counting down.
- A purple sign "⇧ Central Lift · First floor" floats above the spot.
- Within 3 m of it, the banner "Take Central Lift up to First floor. Then line up at a QR code on First floor." appears with **I am on First floor**.

2. Tap **I am on First floor**.

**Expect:** the arrows disappear. The panel says "On First floor: line up at a QR code there. …", and the list has a first-floor code chosen. A **Continue without lining up** button is shown.

3. Go to the printed **1-C02** sign and line up with the camera (as in B3) or by holding the phone to it (as in B4).

**Expect:** "Lined up with Pillar P2 north row, south face." The new route starts at this code: "Walk 8 m.", "now · 11 m · 1 min left".

### B6. Arrive

1. Follow the arrows: about 8 m, then a "→" sign and "Turn right and walk 3 m."

**Expect:** a red ring with a pin and the label "Shop 1-105" at the end. Within 2 m of it: "You have arrived at Shop 1-105." (◎).

### B7. Off the route, tracking lost, drift

1. Tap **Re-align** and line up at 1-C02 again. Walk 5 m sideways away from the arrows and stand still.

**Expect:** about 3 seconds later the banner "You are off the route." with **New route from here**. Tap it: new arrows start near you and lead to the shop.

2. Cover the camera with your hand for three seconds.

**Expect:** "Tracking lost. Move the phone slowly and point it at the floor." appears while it is covered and goes when you uncover it; the arrows stay where they were.

3. Line up at 1-C02 again, walk about 10 m along the arrows and come back. Look at the blue frame.

**Expect:** it still sits on the code or near it. Record how far off it is in the table.

### B8. Leave

1. Tap **Exit**.

**Expect:** the page before AR, with "AR ended." and **Start AR again**.

2. Tap **Back to the map**.

**Expect:** the tab closes and the map tab is shown again. If the tab stays open, it says "You can close this tab now."

3. If you have an iPhone, open a QR address from "Create the data this file needs" in Safari.

**Expect:** the map works with the route, and there is no "View in AR" button.

### Accuracy record (for C-07)

| Line-up | How | Frame offset on the code (cm) | Arrows against a straight line on the floor (degrees, by eye) | Frame offset after 10 m and back (cm) | Notes |
|---|---|---|---|---|---|
| Camera | about 0.5 m, to one side | | | | |
| Camera | about 1 m, straight on | | | | |
| Touch | phone flat on the code | | | | |

For the arrow check, lay a tape line on the floor along a straight piece of the route and estimate the angle between it and the arrows. **Details** shows each line-up's distance, fit (pixels), spread (degrees and centimetres) and whether the floor was found.

## Things that must be refused

| Who | Try | Expect |
|---|---|---|
| Visitor on a computer | <http://127.0.0.1:8000/malls/niagara-city-mall/map>, any route | No "View in AR" button |
| Visitor | Open an AR link from `ar-preview-link.php` 16 minutes after it was printed | 403, nothing shown |
| Visitor | In an AR link change `to=unit%3A1-105` to `to=unit%3AG-101` | 403 |
| Administrator | <http://127.0.0.1:8100/admin/api-keys>, **Revoke** the Smart Mall app key, then open a fresh AR link | "This AR link is no longer valid." and "Go back to the map and tap View in AR again." (create a new key and put it in `.env` afterwards) |
| Any other site | `curl.exe -s -D - -o NUL "<an AR link>"` | `Content-Security-Policy: … frame-ancestors 'none'`: no site may frame the AR page |
| Visitor | In AR, point the camera at a QR code that is not a sign of this mall (any other QR code) | "This QR code is not on this map." and no line-up |
| Visitor on the phone over Wi-Fi by the computer's address (`http://192.168.x.x:8000`, no `adb reverse`) | Open the Map page | No "View in AR" button; an AR link opened that way says "AR needs a secure (https) address. Use the map instead." |

## If something looks wrong

- **No "View in AR" button on the phone:** check these.
  - Open the pages as `http://127.0.0.1:8000` through `adb reverse`, not by the computer's address.
  - Use Chrome, not another browser.
  - Install or update Google Play Services for AR.
  - Show a route first.
- **"AR could not start: …":** allow the camera for `127.0.0.1:8100` in Chrome's site settings, update Google Play Services for AR, and close other apps that use the camera.
- **"Hold still" never counts:**
  - Come closer (about half a metre).
  - Avoid glare on the paper.
  - Print at real size.
  - Check **Details** for "Camera pictures: not available"; if so, line up by holding the phone to the code.
- **The blue frame is beside the printed code:**
  - The print is not 18 cm wide (measure it), or the code hangs at a different height than 1.40 m. Fix it and line up again.
  - Line up by holding the phone to the code (B4) for comparison.
- **Arrows float above or sink below the floor:** the floor was not found ("Floor: not found" in **Details**). Tap **Exit**, **Start AR again** and move the phone over the floor longer.
- **The map or AR page says it has expired:** the page's session lasts 240 minutes and links last 15. Go back to the map and tap **View in AR** again.
- **The phone cannot reach `127.0.0.1` after reconnecting the cable:** run the two `adb reverse` lines again.

## Result

Date: ____  Tester: ____  Phone and Chrome version: ____  Pass / Fail: ____  Notes: ____
