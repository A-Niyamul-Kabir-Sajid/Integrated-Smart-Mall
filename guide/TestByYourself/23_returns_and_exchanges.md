# 23 Returns and exchanges

**Covers:** EPIC-10 ISM-107 / ISM-108 (return and exchange requests), ShopOwnerOperations
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/ReturnTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 20 minutes

## What you are proving

The buyer of a sale can ask to return or exchange goods within 14 days; the shop approves or declines and completes the return when the goods are back, optionally putting them into stock; a return of every line for a refund turns the sale into a refunded one; a sale with returns cannot be voided; nobody else can see or start a return. The refund is only recorded: nothing is paid through the site.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| C | `customer@demo.test` | Buyer |
| S | `staff@demo.test` | Cafe Brew cashier |
| O | `owner@demo.test` | Cafe Brew owner |
| X | `test@example.com` | A second customer |

### Create the data this file needs

1. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep Cafe Brew's `workspace` URL as `CAFE_WS`.
2. As S open `CAFE_WS`/sales and **Record a sale** for customer `customer@demo.test`: Cold Coffee quantity `3`, discount `30`; Butter Croissant quantity `1`. The sale total is the sum minus 30.
3. Open the sale and note its page URL as `SALE` (http://127.0.0.1:8000/workspace/shops/<id>/sales/<id>).

## Steps

### 1. Ask for a return (C)

1. Sign in as C. Open <http://127.0.0.1:8000/account/purchases> and the Cafe Brew sale.
2. Under **Return or exchange**, put `1` next to Cold Coffee, keep **Return for a refund**, reason `The coffee tasted burnt.`, **Send request**.

**Expect:** the return page shows `RET-XXXXXXXX`, status `Waiting for the shop`, the value (one third of the Cold Coffee line after its discount) and **Cancel this request**. The list <http://127.0.0.1:8000/account/returns> shows it.

3. Try to return `3` more Cold Coffee: refused (`only 2 can still be returned`).

### 2. The shop decides (S)

1. As S open `CAFE_WS`/returns and the request. Enter reason `x` and **Decline**: refused. Click **Approve** with the note `Bring the cup too.`.

**Expect:** status `Approved, bring the goods`; as C the notification list holds `Your return was approved`.

2. Click **Goods received, complete** with **Put the goods back into stock** ticked.

**Expect:** `Completed`, `The goods were put back into stock`. If Cold Coffee stock is tracked (guide 21) its quantity is one higher. The sale is still `Completed` because only part of it came back.

### 3. Return everything for a refund (C, S)

1. As C request a return of **all remaining** quantities of every line, reason `Changed my mind completely.`
2. As S approve and complete it.

**Expect:** the sale page now says `Refunded`. Any review code for that sale no longer works (guide 10).

### 4. Exchange (C, S)

1. On a fresh sale (record another one as S), request an **Exchange** of one line as C, then approve and complete as S.

**Expect:** the sale stays `Completed` (an exchange never refunds the sale). The shop records the replacement as a new sale.

### 5. A return recorded at the counter (S)

1. Record a walk-in sale (leave the customer empty). Open it, use **Return or exchange** on that page for one line, reason `Customer changed their mind`.

**Expect:** the return exists already `Approved` (recorded at the counter); complete it from `CAFE_WS`/returns.

### 6. Voiding is blocked once returns exist (S)

1. On a sale that has a return, try **Void the sale**.

**Expect:** refused (`This sale has returns...`).

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| X | the return page of C's return, or its cancel button | 404 |
| X | `POST` a return for C's sale | 404 |
| C | `CAFE_WS`/returns | 403 |
| Owner of another shop | `CAFE_WS`/returns | 403 |
| C | a return for a sale older than 14 days or already voided/refunded | The form says the window is closed or the sale is not completed |
| C | more than 3 open return requests on one sale | Error |
| C | a reason shorter than 5 letters, or no item selected | Error, nothing saved |
| S | complete a return that was never approved | Error |

## If something looks wrong

- No **Return or exchange** form on the receipt: the sale is older than 14 days or not completed (the page says why).
- Return value looks too low: discounts are shared over the line; the value is the part of the line total that comes back.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
