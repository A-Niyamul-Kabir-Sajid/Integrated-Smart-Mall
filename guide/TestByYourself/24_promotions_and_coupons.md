# 24 Promotions and coupons

**Covers:** EPIC-10 ISM-107 / ISM-109 (promotions and coupons), ShopOwnerOperations
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/PromotionTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 20 minutes

## What you are proving

A manager or owner creates a storewide offer (shown on the shop page, applied to every order automatically) and a coupon (applied only when the customer types the code); discounts, limits and dates are enforced; a coupon use is counted once and given back when the order is cancelled; the discount carries into the recorded sale; cashiers and other shops cannot touch promotions.

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
| S | `staff@demo.test` | Cafe Brew cashier (**must not** reach promotions) |
| C | `customer@demo.test` | Customer |
| X | `test@example.com` | A second customer |

### Create the data this file needs

1. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep Cafe Brew's `workspace` URL as `CAFE_WS`. Promotions are at `CAFE_WS`/promotions.

## Steps

### 1. A storewide offer (O)

1. Sign in as O, open `CAFE_WS`/promotions, **New promotion**.
2. Title `Eid special`, description `Warm welcome`, Discount `Percent off the order`, value `10`, leave the coupon code empty, minimum `0`, starts one hour ago, ends in 7 days, **Create promotion**.

**Expect:** the list shows `Eid special`, `10%`, `Storewide`, `Running`, `Used 0`.

3. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew> signed out.

**Expect:** an **Offers** box with `Eid special: 10% off ... until <date>` and the description.

### 2. The offer applies on its own (C)

1. As C open the shop page, **Order from this shop**, Espresso quantity `2`, set **Collect by** in about 3 hours, **Send order**.

**Expect:** the order page shows the lines, a `Discount (Eid special)` row of 10 percent, and the lower **To pay at the shop** total.

### 3. A coupon (O, C, X)

1. As O create a second promotion: title `Members week`, **Fixed amount off the order**, value `50`, coupon code `member-50` (it is stored as `MEMBER-50`), minimum order `300`, **Create promotion**.

**Expect:** the shop page (signed out) does **not** show `Members week` or its code. The list shows `Code MEMBER-50`.

2. As C order Espresso quantity `1` with coupon `MEMBER-50`: refused (the order is below the minimum; the message shows ৳300).
3. Order enough to reach 300 (for example Espresso quantity `6`) with coupon `member-50`.

**Expect:** the order shows the discount, the coupon and the new total. In `CAFE_WS`/promotions the promotion shows `Used 1`.

4. As C try the same code on another order: refused (`You have already used this coupon.`).
5. As X use the code `WRONG`: refused with `This coupon code is not valid right now.` (the same message for every unknown, paused or expired code).
6. As X use `MEMBER-50` on a big enough order: accepted (each customer may use it once).

### 4. Cancelling gives the use back (C, O)

1. As C cancel the order from step 3.3 (**Cancel this order**).

**Expect:** the promotion list shows `Used 1` instead of 2; C may use the code again.

### 5. The discount reaches the sale (S)

1. As S open `CAFE_WS`/orders, accept the order from step 2, mark it ready and hand it over with its pickup code (guide 22).

**Expect:** the recorded sale and receipt show the line discount, and the total equals the order total.

### 6. Pause, resume, end (O)

1. Pause `Eid special`: the list says `Paused` and the shop page no longer shows the offer; a new order gets no discount. **Resume** restores it.
2. Click **End now** on `Members week`: the state becomes `Ended`; the code no longer works.
3. Try to create a promotion with the same coupon code as an existing one: refused (`already uses that coupon code`).

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| S (Cashier) | `CAFE_WS`/promotions | 403 |
| C | `CAFE_WS`/promotions | 403 |
| Owner of another shop | `CAFE_WS`/promotions or a promotion id of Cafe Brew under their own shop URL | 403 / 404 |
| O | percent above 90, value 0, end date before start, code with spaces | Error, nothing saved |
| O | a total-use limit lower than the uses so far | Error |
| C | an expired, paused, used-up or unknown code | The same generic error |

## If something looks wrong

- Times are in the mall timezone (Asia/Dhaka); a promotion that "starts in the future" shows `Scheduled`.
- The offer is missing on the shop page: the shop must be active and the offer must have no coupon code.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
