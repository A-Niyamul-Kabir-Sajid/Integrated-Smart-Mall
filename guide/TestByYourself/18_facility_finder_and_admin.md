# 18 Facility finder and facility administration

**Covers:** EPIC-09 ISM-92 (ISM-93, ISM-94) and ISM-98 (ISM-99), MallShopDirectory facilities, ParkingFacilities screens, decision D-013
**Automated tests:** `tests/Feature/Modules/MallShopDirectory/Services/FacilityServiceTest.php`, `tests/Feature/Modules/ParkingFacilities/FacilityAdminTest.php`, `tests/Feature/Modules/ParkingFacilities/FacilityFinderTest.php`, `tests/Feature/Security/RouteProtectionTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 10 minutes

## What you are proving

Anyone can find washrooms, prayer rooms, information desks, exits, ATMs and lifts by type and floor, including whether they are step-free or temporarily closed. Mall administrators, and only they, add and edit facilities; a facility can only sit on an active floor of its own mall.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `mall.admin@demo.test` (Niagara City Mall administrator), `customer@demo.test`, `owner@demo.test`.

### Create the data this file needs

Nothing beyond the demo seed: floors B1 (Basement Parking), G and F1 with eleven facilities, one of them (Baby Care Room) temporarily closed.

## Steps

### 1. Find facilities as a guest

1. Signed out, open <http://127.0.0.1:8000/malls/niagara-city-mall> and press **Facilities** in the header.

**Expect:** <http://127.0.0.1:8000/malls/niagara-city-mall/facilities> lists facilities grouped by floor (Basement Parking, Ground Floor, First Floor). Each shows its type, a "Step-free" badge when accessible, and a short location note (e.g. Washroom "Behind the Food Court entrance"). Baby Care Room shows **Temporarily closed**.

### 2. Filters

1. Type **Toilet** → **Filter**. **Expect:** only the two Washrooms (G and F1).
2. Clear the type, Floor **First Floor** → **Filter**. **Expect:** only First Floor facilities.
3. Tick **Step-free only** with Floor First Floor. **Expect:** the Prayer Room disappears (it is not step-free).
4. Clear filters, tick **Open only**. **Expect:** Baby Care Room disappears.
5. Open <http://127.0.0.1:8000/malls/niagara-city-mall/facilities?type=casino>. **Expect:** refused (redirect back with an error), no crash.

### 3. Keyboard and screen reader

1. Press Tab from the top of the facilities page.

**Expect:** focus moves through the header, Type, Floor, the two checkboxes and **Filter** in order; every control has a visible label.

### 4. Administer facilities

1. Sign in as `mall.admin@demo.test`, open <http://127.0.0.1:8000/account>.

**Expect:** under "Malls you administer", Niagara City Mall has **Facilities** and **Review moderation**.

2. Press **Facilities** (<http://127.0.0.1:8000/admin/malls/...ID.../facilities>).

**Expect:** a table of all eleven facilities and below it **Parking zones** (B1-A, B1-B).

3. Press **Add facility**: Name `Second ATM`, Type **ATM**, Floor First Floor, Where exactly `Next to Book Nook`, tick Step-free, press **Add facility**.

**Expect:** "Second ATM was added." and the row in the table. On the customer page (step 1 URL) it appears under First Floor.

4. Press **Edit** on Second ATM, choose Status **Archived**, press **Save facility**.

**Expect:** the row is greyed out as Archived and it disappears from the customer page.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| guest | the admin URL from step 4.2 | redirected to sign in |
| `customer@demo.test` | the admin URL from step 4.2 | **403 Forbidden** |
| `owner@demo.test` | the admin URL from step 4.2 | **403 Forbidden** (shop owners do not administer the mall) |
| `mall.admin@demo.test` | on Add facility, change the Floor option value with F12 to an id of another mall's floor (or any random UUID) | "The floor belongs to another mall.", nothing saved |

## If something looks wrong

- No **Facilities** link in the header: run `npm run build` and hard-reload.
- Facilities are not on a map yet: placing them on the indoor map waits for EPIC-05 (MapsNavigation).

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
