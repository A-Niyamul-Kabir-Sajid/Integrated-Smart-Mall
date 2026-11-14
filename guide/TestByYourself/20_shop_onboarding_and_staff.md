# 20 Shop onboarding, approval, profile and staff

**Covers:** EPIC-10 ISM-101 (ISM-102, ISM-103), ShopOwnerOperations
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/ShopApplicationTest.php`, `.../ShopProfileTest.php`, `.../StaffAccessTest.php`, `tests/Feature/Seeders/DemoAccountsSeederTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 25 minutes

## What you are proving

A customer can apply to open a shop; the mall administrator approves or rejects it; only an approved shop is operational; the owner edits the profile and opening hours and invites staff with a work function; staff only reach the areas their function allows; the mall can suspend and reopen a shop.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>; use a private window per account:

| Short | Account | Role |
|---|---|---|
| C | `customer@demo.test` | Applicant |
| M | `mall.admin@demo.test` | Mall administrator of Niagara City Mall |
| O | `owner@demo.test` | Owner of Cafe Brew |
| S | `staff@demo.test` | Cafe Brew staff, function **Cashier** |
| G | `manager@demo.test` | Cafe Brew staff, function **Manager** |

### Create the data this file needs

1. Register a second account at <http://127.0.0.1:8000/register>: name `New Cook`, email `new.cook@example.test`, password `password-12345` twice. You use it in step 6. Sign out.
2. Print the Cafe Brew ids: `php artisan tinker guide/TestByYourself/scripts/show-links.php` and keep the `workspace` URL of Cafe Brew as `CAFE_WS` (it looks like http://127.0.0.1:8000/workspace/shops/<id>).

## Steps

### 1. Apply to open a shop (C)

1. Sign in as C. Open <http://127.0.0.1:8000/account/shop-applications>: it says you have not applied yet.
2. Click **Open a shop** (<http://127.0.0.1:8000/account/shop-applications/create>). Mall `Niagara City Mall`, name `Corner Books`, any category, contact `01712345678`, a short description and note. Click **Send application**.

**Expect:** you land on the applications page with `Corner Books` and the badge `Waiting for a decision`. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops> and search `Corner`: the shop is **not** listed (it is still a draft).

### 2. The draft shop is not operational yet (C)

1. On the applications page click **Open the shop workspace**.

**Expect:** the workspace shows `Waiting for mall approval`, a note that stock, orders, promotions and support open once the shop is active, and links to Catalogue, Reviews, Shop profile and Staff. There is **no** Stock or Orders link.

### 3. The mall approves (M)

1. Sign in as M. Open <http://127.0.0.1:8000/account> and click **Shop applications** next to the mall, or go straight to <http://127.0.0.1:8000/admin/malls/> followed by the mall id from `show-links.php` and `/shop-applications`.
2. `Corner Books` is pending. Click **Approve**.

**Expect:** the flash says the shop was approved; the badge turns `Approved`. Sign in as C: the notification list holds `Your shop was approved`; the workspace now shows the status `Active` and any areas the owner may use. <http://127.0.0.1:8000/malls/niagara-city-mall/shops> now lists `Corner Books`.

### 4. A rejection needs a reason (M and C)

1. As C, send a second application named `Rejected Shop`.
2. As M, in **Reject**, enter `no` and click **Reject**. Expect an error and the application still pending.
3. Enter `The unit is already let.` and click **Reject**.

**Expect:** the application shows `Rejected` with the reason. As C, <http://127.0.0.1:8000/account/shop-applications> shows `Not approved` and the reason; no workspace link is offered. The shop never appears in the public directory.

### 5. Profile and opening hours (O)

1. Sign in as O and open `CAFE_WS`, then **Shop profile and opening hours** (`CAFE_WS`/profile).
2. Change the description and click **Save profile**. Expect `The shop profile was saved.` The shop address (slug) does not change.
3. Set Monday opens `09:00` closes `21:00`; Friday opens `18:00` closes `02:00` with **Next day** ticked; leave Tuesday empty. Click **Save opening hours**.
4. Set Monday opens `10:00` and leave closes empty, then save.

**Expect:** step 3 saves; the public shop page <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew> shows Monday 09:00 to 21:00 and Tuesday closed. Step 4 is refused with `Enter both an opening and a closing time...` and nothing changes.

### 6. Invite staff (O) and accept (new account)

1. As O open `CAFE_WS`/staff. In **Invite by email** enter `new.cook@example.test`, function `Catalogue editor`, **Send invitation**.

**Expect:** `An invitation was sent` and, once, a yellow box with the invitation link. Copy it. The pending list shows the address. (With the default log mail driver the email text is in `storage/logs/laravel.log`.)

2. Open the link as a signed-out visitor: you are sent to sign in. Sign in as `new.cook@example.test`; open the link again and click **Accept the invitation**.

**Expect:** you land on the Cafe Brew workspace; your function is `Catalogue editor`; the menu shows Catalogue, Reviews and, once EPIC-10 stock exists, Stock. There is no Staff or Shop profile link.

3. Open the link again.

**Expect:** `This invitation cannot be used.`

### 7. Work functions limit areas (S, G, new account)

1. Sign in as S (Cashier) and open `CAFE_WS`. Expect `Your function: Cashier`. Open `CAFE_WS`/staff and `CAFE_WS`/profile: both answer 403.
2. Sign in as G (Manager): the same two pages answer 403 (only owners manage staff and the profile).
3. As O open `CAFE_WS`/staff, change `New Cook` to `Manager`, click **Change**; then **Remove** `New Cook`.

**Expect:** the change and removal are flashed. Signed in as `new.cook@example.test`, `CAFE_WS` now answers 403.

### 8. Suspend and reopen (M, O)

1. As M open the shop applications page. Under **Shops in the mall** find `Cafe Brew`, enter reason `x` and **Suspend**: refused. Enter `Unpaid service charge.` and **Suspend**.
2. As O open `CAFE_WS`.

**Expect:** the status reads `Suspended` with `The mall suspended this shop`. Stock, orders and the other areas are not offered. The shop page is still public.

3. As M click **Reactivate** next to Cafe Brew. As O, `CAFE_WS` shows `Active` again.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | <http://127.0.0.1:8000/account/shop-applications> | Redirect to sign in |
| C | the mall's shop applications page (`/admin/malls/<id>/shop-applications`) | 403 |
| O | the same page | 403 |
| M | approve a decided application again | Error `already decided`, nothing changes |
| M (another mall) | decide an application of this mall | 403 or 404 |
| O of Cafe Brew | `/workspace/shops/<another shop id>/staff` | 403 |
| A stranger | open an invitation link with another email | Page says it was sent to a different address; accept is refused |
| O | invite an email that already works at the shop | Error, no invitation |
| Applicant | a fourth pending application | Error `You already have 3 applications waiting` |

## If something looks wrong

- 429 Too Many Requests on applying or inviting: wait one minute (applications are limited to 10 per hour).
- The invitation link box is gone after a reload: it is shown once; revoke the invitation and send a new one.
- No styles: run `npm run build`.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
