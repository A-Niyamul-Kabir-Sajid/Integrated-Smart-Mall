# 22 Orders, click and collect, counter sales and receipts

**Covers:** EPIC-10 ISM-107 / ISM-108 (orders, reservations, pickup, purchase registration, receipts), ShopOwnerOperations
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/OrderServiceTest.php`, `.../OrderWorkflowTest.php`, `.../SaleAndReceiptTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 30 minutes

## What you are proving

A customer orders from a shop and gets a pickup code; the shop accepts, prepares and hands over against the code; handing over records one sale at the prices the customer agreed to, takes the goods out of stock and issues a receipt; staff can also ring up a counter sale, issue a one-time review code, and void a mistake; nobody can see or change another customer's or another shop's orders.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>; use a private window per account:

| Short | Account | Role |
|---|---|---|
| C | `customer@demo.test` | Customer who orders |
| S | `staff@demo.test` | Cafe Brew cashier |
| O | `owner@demo.test` | Cafe Brew owner |
| X | `test@example.com` | A second customer with no role |

### Create the data this file needs

1. Run `php artisan tinker guide/TestByYourself/scripts/show-links.php` and keep Cafe Brew's `workspace` URL as `CAFE_WS` (http://127.0.0.1:8000/workspace/shops/<id>). The order inbox is `CAFE_WS`/orders; counter sales are `CAFE_WS`/sales.
2. Track Espresso stock: sign in as O, open `CAFE_WS`/stock, **Manage** on Espresso, record a delivery of `10`. (See guide 21 for the screen.)

## Steps

### 1. Place an order (C)

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew>. Click **Order from this shop**.
2. Enter quantity `2` for Espresso (leave the others at 0), choose **Order ahead and collect**, set **Collect by** to about 3 hours from now, note `Extra hot`, **Send order**.

**Expect:** the order page shows an `ORD-XXXXXXXX` number, status `Waiting for the shop`, a six-character **pickup code**, the line `Espresso`, the total, and **Cancel this order**. Note the pickup code.

3. Open <http://127.0.0.1:8000/account/orders>: the order is listed.

### 2. The shop works the order (S)

1. Sign in as S and open `CAFE_WS`/orders. The order is listed.
2. Open it: **Accept**, then **Mark ready for pickup**.

**Expect:** each step flashes a message; the history lists `Waiting for the shop`, `Accepted`, `Ready for pickup` with your name. As C, the notification list holds `Your order was accepted` and `Your order is ready`.

3. Enter a wrong pickup code `ZZZZZZ` and **Hand over and record the sale**.

**Expect:** refused (`The pickup code does not match this order.`); nothing recorded; stock unchanged.

4. Enter the real pickup code (lower case is fine) and hand over.

**Expect:** the order is `Collected` with a link **Open the recorded sale and receipt**. Stock of Espresso drops by 2 (`CAFE_WS`/stock). The customer's page shows **Open your receipt**.

### 3. Receipts (C and S)

1. As S open the sale from the order page: it shows the line, the total, the receipt number `RC-YYYYMMDD-XXXXXX` and the link **Open the receipt**.
2. As C open <http://127.0.0.1:8000/account/purchases>: the sale is listed. Open it: a receipt with shop name, lines, total and **Print**.
3. As X open the same receipt URL: 404.

### 4. A counter sale (S)

1. As S open `CAFE_WS`/sales, **Record a sale**.
2. Customer `customer@demo.test`, quantity `1` for Cold Coffee, discount `10`, leave the receipt reference empty, **Record the sale**.

**Expect:** the sale page shows a generated reference `W-YYYYMMDD-XXXXXX`, the total reduced by 10, a receipt number and, for the line, **Issue review code**.

3. Click **Issue review code**.

**Expect:** a yellow box shows the code once. Reload the page: the code is gone and the line says `Code issued`. (Use it as in guide 10 to write a verified review.)
4. Record a second sale with the customer field empty (a walk-in sale): it has no **Issue review code** button.
5. Record a sale with customer `nobody@example.test`: refused (`No active customer account matches...`).

### 5. Void a mistake (S)

1. On the walk-in sale click **Void the sale** with an empty reason: refused. Enter `Rung up twice` and void.

**Expect:** status `Voided`; for tracked products the quantity returns to stock; voiding again is refused.

### 6. Decline and cancel (C, S)

1. As C place another order. As S open it, enter reason `x` and **Decline**: refused. Enter `We are closing early today.` and decline.

**Expect:** as C the order shows `Declined by the shop` and the reason; no cancel button.

2. As C place a third order and click **Cancel this order**: status `Cancelled`.

### 7. Orders that are never collected

1. In the project folder run `php artisan orders:expire-overdue`.

**Expect:** it prints `0 order(s) expired.` for fresh orders. (Orders more than 24 hours past their pickup time become `Expired`; the same command runs daily when the scheduler runs.)

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | <http://127.0.0.1:8000/account/orders> | Redirect to sign in |
| X | the order page of C's order | 404 |
| X | cancel C's order | 404 |
| C | `CAFE_WS`/orders, `CAFE_WS`/sales | 403 |
| A catalogue editor of Cafe Brew | `CAFE_WS`/orders | 403 |
| Owner of another shop | `CAFE_WS`/orders | 403 |
| S | hand over with the wrong code | Error, order unchanged |
| S | hand over when stock is below the ordered quantity (count the shelf to 1 first) | Error `has only 1 in stock`, no sale, no receipt, order unchanged |
| C | an order of 0 items, quantity above 20, pickup time in 5 minutes or in 9 days | Error, nothing saved |
| C | more than 10 open orders | Error |
| Anyone | order from a suspended shop (guide 20, step 8) | Error `not taking orders right now` |
| S | review code on a walk-in sale | No button; the server refuses too |

## If something looks wrong

- 429 Too Many Requests while handing over: the pickup-code attempts are limited to 10 per minute.
- A product is missing from the order form: it is archived or out of stock (`unavailable`).
- No styles: run `npm run build`.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
