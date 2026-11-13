# 26 Maintenance requests to the mall

**Covers:** EPIC-10 ISM-107 / ISM-109 (maintenance requests), ShopOwnerOperations
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/MaintenanceRequestTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 15 minutes

## What you are proving

A manager or owner of an active shop reports a problem to the mall; the mall's administrators move it to acknowledged, in progress and resolved (the mall-side screen arrives with EPIC-11, so this file uses a helper); the shop confirms the fix or reopens it; every step is in the history; cashiers and other shops cannot see or change requests.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| O | `owner@demo.test` | Cafe Brew owner |
| G | `manager@demo.test` | Cafe Brew manager |
| S | `staff@demo.test` | Cafe Brew cashier (**must not** reach maintenance) |
| M | `mall.admin@demo.test` | Mall administrator (the mall office) |

### Create the data this file needs

1. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep Cafe Brew's `workspace` URL as `CAFE_WS`. Maintenance is at `CAFE_WS`/maintenance.
2. The mall's side has no screen yet. To play the mall office, use tinker with the request number you will see in step 1, for example `php artisan tinker --execute 'app(\App\Modules\ShopOwnerOperations\Services\MaintenanceRequestService::class)->updateStatus(\App\Modules\AccountsAccess\Models\User::where("email","mall.admin@demo.test")->first(), \App\Modules\ShopOwnerOperations\Models\MaintenanceRequest::where("request_number","MNT-XXXXXXXX")->first(), \App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus::Acknowledged);'` (replace `MNT-XXXXXXXX` and the status: `Acknowledged`, `InProgress`, then `Resolved` with a fourth argument such as `"Replaced the washer"`).

## Steps

### 1. Raise a request (O)

1. Sign in as O and open `CAFE_WS`/maintenance.
2. Title `Leaking tap`, category `Plumbing`, priority `Urgent`, details `The tap in the back room leaks.`, **Send to the mall**.

**Expect:** the request page shows `MNT-XXXXXXXX`, status `Sent to the mall`, the details and **Cancel this request**. As M the notification list holds `Maintenance request from Cafe Brew`.

### 2. The mall works it (tinker, as M)

1. Run the helper three times: `Acknowledged`, `InProgress`, then `Resolved` with the note `Replaced the washer`.

**Expect:** after each call O's notification list gains `Maintenance update`; reloading the request shows `Acknowledged`, `Being fixed`, and finally `Fixed, please confirm` with `What the mall did: Replaced the washer`. Marking it resolved without a note is refused.

### 3. Confirm or reopen (O)

1. On the resolved request enter `Still dripping` and click **Reopen**: the status returns to `Being fixed`.
2. Resolve it again with the helper, then click **It is fixed, close the request**.

**Expect:** status `Closed`; the history lists every step with the actor and notes.

### 4. Cancel (O)

1. Raise a second request (`Broken light`). Before the mall touches it click **Cancel this request**.

**Expect:** `Cancelled`. After the mall has started work (`Being fixed`) the cancel button is gone and a cancel attempt is refused.

### 5. Access (G, S)

1. As G (Manager) open `CAFE_WS`/maintenance: allowed, and G sees the same requests.
2. As S (Cashier) open it: 403, and the workspace home has no Maintenance link.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `CAFE_WS`/maintenance | Redirect to sign in |
| S (Cashier) | `CAFE_WS`/maintenance or a new request | 403 |
| Owner of another shop | `CAFE_WS`/maintenance, or a Cafe Brew request id under their own shop URL | 403 / 404 |
| O | mark the request resolved themselves, or move it to a status the shop cannot set | No such button; the service refuses |
| O | an empty title, a one-word details, an invalid category, more than 20 open requests | Error, nothing saved |
| Another mall's administrator | change this request | Refused |

## If something looks wrong

- 429 Too Many Requests: requests are limited to 10 per minute together with support messages.
- The mall-side screen is part of EPIC-11 (ISM-117); until then the helper above plays the mall office.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
