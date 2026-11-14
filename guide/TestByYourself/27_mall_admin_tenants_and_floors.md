# 27 Mall administration: tenants, floors, categories and the activity log

**Covers:** EPIC-11 ISM-110 / ISM-111 / ISM-112, MallOwnerAdmin (with MallShopDirectory services)
**Automated tests:** `tests/Feature/Modules/MallOwnerAdmin/TenantAdministrationTest.php`, `tests/Feature/Modules/MallOwnerAdmin/MallAdminScreensTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 25 minutes

## What you are proving

The mall office, and only the mall office, can create and edit shops, place them in units, name their owners, open and archive them, manage floors, edit the mall's own details, and see every one of those changes in an activity log. Shop accounts, customers and another mall's administrators are refused.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep the `Admin home` line as `ADMIN` (it ends in the mall id).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| M | `mall.admin@demo.test` | Mall administrator of the demo mall |
| P | `platform@demo.test` | Platform administrator |
| O | `owner@demo.test` | Cafe Brew owner (must be refused) |
| C | `customer@demo.test` | Customer (must be refused) |

### Create the data this file needs

1. Register a future tenant at <http://127.0.0.1:8000/register>: name `Rina Tenant`, email `rina.tenant@example.test`, password `password-12345` (twice). Sign out afterwards.

## Steps

### 1. The administration home (M)

1. Sign in as M and open `ADMIN`.

**Expect:** the page `Mall administration` shows the queues `Shop applications`, `Maintenance requests`, `Reported reviews` with counts, tenant counts (active, draft, suspended, archived shops, active floors) and the workspace links (shops, floors, facilities, activity log, mall details). The account page <http://127.0.0.1:8000/account> lists an `Administration` link for the mall.

### 2. Create a shop for a tenant (M)

1. Open `ADMIN`/shops and click **Add a shop**.
2. Shop name `Corner Books`, public address `corner-books`, any category, floor `Ground floor`, unit code `g-07`, area `42.5`, owner `rina.tenant@example.test`. Click **Create shop**.

**Expect:** you land on the shop's page with the message `Corner Books was created as a draft.` The unit shows as `G-07`, Rina is listed under Owners, the status is `Draft`, and the shop is **not** visible at <http://127.0.0.1:8000/malls/niagara-city-mall/shops> yet. Signed in as Rina, the notification list holds `You were made owner of Corner Books`.

### 3. Refusals on creation (M)

1. Add another shop with address `Bad Slug!`: refused with `A shop slug may only contain lower-case letters...`.
2. Add another with owner `nobody@example.test`: refused with `No active account has that email address or mobile number...`, and no half-created shop appears in the list.
3. Add another with floor `Ground floor` and unit code `G-07`: refused with `Unit G-07 on this floor is already assigned.`

### 4. Edit, activate (M)

1. On Corner Books change the description and category and click **Save shop**.
2. Remove Rina as owner: refused only once the shop is active and she is the last owner (try it after activating).
3. Click **Activate the shop**.

**Expect:** the status becomes `Active`, the shop now appears in the public directory, Rina gets `Corner Books is active`. Activating a shop with no owner is refused (`Name an owner for the shop before activating it.`); try it with a second draft shop that has no owner.

### 5. Units and owners (M)

1. Release unit `G-07`, then assign `G-07` to a different shop: it works, because the released unit is taken over.
2. Add a second owner by email or mobile number, then remove Rina: allowed. Removing the last owner of an active shop is refused.

### 6. Archive (M)

1. On Corner Books enter the reason `Lease ended` and click **Archive the shop**.

**Expect:** you return to the shop list, the shop is `Archived`, its units are released, it is gone from the public directory, and its edit page offers no save, assign or archive buttons. A reason shorter than 5 characters is refused. A shop with an open order (accepted but not handed over) is refused with `The shop still has open orders...`.

### 7. Floors (M)

1. Open `ADMIN`/floors. Add floor code `F9`, name `Roof garden`, order `9`, elevation `30`.
2. Rename it `Sky garden`, change the order, **Save**. The code `F9` stays.
3. Click **Archive floor** on a floor that still has shops (for example the ground floor): refused with `Move or release the shop units on this floor before archiving it.`
4. Archive `F9` (empty): allowed; **Restore floor** brings it back.
5. Add another floor with code `F9`: refused (`This mall already has a floor with code F9.`).

### 8. Mall details (M)

1. Open `ADMIN`/profile. Change the address, **Save mall details**: saved. Choose an invalid time zone through the browser tools: refused.
2. A draft mall can be made active with the button under `Visibility`; an archived mall cannot be edited.

### 9. Shop categories (P)

1. Sign in as P and open <http://127.0.0.1:8000/admin/categories>. Add `Toys` with slug `toys`; rename it `Toys and games`; try to put it inside itself: refused (`A category cannot be placed under itself...`).

### 10. The activity log (M)

1. Open `ADMIN`/activity.

**Expect:** rows for everything above (`shop.created`, `shop.status_changed`, `shop.unit_assigned`, `shop.unit_released`, `floor.created`, `floor.archived`, `mall.profile_changed`, `access.role_assigned`) with time (Dhaka), the actor's name, and details. The **Area** filter `floor` shows only floor rows. There is no edit or delete button anywhere.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `ADMIN`, `ADMIN`/shops, `ADMIN`/floors, `ADMIN`/activity | Redirect to sign in |
| C (customer) | the same URLs | 403 |
| O (shop owner) | the same URLs, or posting to `ADMIN`/shops | 403, nothing saved |
| Administrator of another mall | the same URLs for this mall | 403 |
| M | a shop or floor id of another mall inside this mall's URL | 404 |
| M | <http://127.0.0.1:8000/admin/categories> | 403 (platform administrators only) |

## If something looks wrong

- 429 Too Many Requests: administration screens allow 60 requests a minute.
- The category list is shared by every mall; only the platform administrator changes it.
- Suspending a shop and approving applications stay on the shop applications page (guide 20).

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
