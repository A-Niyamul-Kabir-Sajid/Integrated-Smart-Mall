# 21 Stock, low-stock alerts and product options

**Covers:** EPIC-10 ISM-104 (ISM-105, ISM-106), ShopOwnerOperations
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/StockServiceTest.php`, `.../StockWorkspaceTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 20 minutes

## What you are proving

A shop starts tracking a listing by recording a delivery or a count; every change is written to an unchangeable history whose balance always matches the quantity; stock never goes below zero; the listing shows "out of stock" when it reaches zero; the owner is alerted when stock falls to the alert level; only the right staff may touch stock; and a shop can add another option (size) to a product it alone sells.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>; use a private window per account:

| Short | Account | Role |
|---|---|---|
| O | `owner@demo.test` | Owner of Cafe Brew |
| G | `manager@demo.test` | Cafe Brew staff, Manager |
| S | `staff@demo.test` | Cafe Brew staff, Cashier (**must not** reach stock) |
| C | `customer@demo.test` | Customer, no shop role |

### Create the data this file needs

1. Run `php artisan tinker guide/TestByYourself/scripts/show-links.php`. From the **SHOPS** block keep Cafe Brew's `workspace` URL as `CAFE_WS` (http://127.0.0.1:8000/workspace/shops/<id>). The stock list is `CAFE_WS`/stock.
2. Cafe Brew already sells Cold Coffee, Espresso and Butter Croissant. You use the listing of **Espresso**.

## Steps

### 1. Start tracking (O)

1. Sign in as O and open `CAFE_WS`/stock.

**Expect:** the Cafe Brew listings, each with `Not tracked` in the On hand column and a Manage link.

2. Click **Manage** on Espresso. It says `Not tracked yet`.
3. Under **Record a delivery** enter quantity `12`, note `Invoice 1042`, **Add to stock**.

**Expect:** `The delivery was added to stock.`; the page says `On hand: 12`; **Stock history** has one line `Stock received +12, balance 12` with your name and the note.

### 2. Count the shelf (O)

1. Under **Count the shelf** enter counted `9`, reason `Stocktake`, **Save the count**.
2. Enter counted `9` again with reason `Again`, save.
3. Enter counted `5` with an empty reason, save.

**Expect:** step 1 adds `Stock count adjusted -3, balance 9`. Step 2 is refused (`The count equals the quantity already on hand.`). Step 3 is refused (a reason is required). The history still has two lines.

### 3. The alert level (O)

1. Under **Alert me when stock is at or below** enter `6`, **Save alert level**.

**Expect:** `The low-stock alert level was saved.`
2. Open `CAFE_WS`/stock: Espresso shows 9 without the Low badge. Record a count of `5`, reason `Spoiled`.

**Expect:** the Low badge appears on the list, the page `CAFE_WS`/stock?low=1 lists Espresso, the workspace home `CAFE_WS` shows `1 item low` next to Stock, and the account page notification list for O holds `Low stock`.

3. Set the alert level to `0`.

**Expect:** the Low badge and the home hint disappear.

### 4. Zero stock marks the listing out of stock (O)

1. Count `0`, reason `Sold out on the shelf`.
2. Open the product search <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=espresso> and click Espresso to see its offers.

**Expect:** Espresso shows `Out of stock` for Cafe Brew. Record a delivery of `3`: it shows `Available` again.

### 5. Add another option of a product (O)

1. Open `CAFE_WS`/catalogue, then **Edit** on the Butter Croissant listing (only Cafe Brew sells it).
2. In **Add another option of this product** enter option `Large`, code `large`, SKU `CB-CRO-L`, price `120`, availability `Available`, **Add option**.

**Expect:** the catalogue lists the new line, the column **In stock** shows `—` for untracked lines and the number for tracked ones.
3. Repeat step 2 with the same code `large` and another SKU: refused (`This product already has the option large.`).
4. Open **Edit** on a product other shops also sell (Mineral Water 500 ml): there is **no** "Add another option" section.

### 6. Who may touch stock (G, S, C)

1. As G (Manager) open `CAFE_WS`/stock and receive `1` of Espresso: allowed.
2. As S (Cashier) open `CAFE_WS`/stock: 403. The workspace home `CAFE_WS` has no Stock link.
3. As C open `CAFE_WS`/stock: 403.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `CAFE_WS`/stock | Redirect to sign in |
| S (Cashier) | `CAFE_WS`/stock and any stock action | 403 |
| O of another shop | `CAFE_WS`/stock | 403 |
| O | quantity `0`, `-4` or `abc` in a delivery | Error, nothing saved |
| O | a count below zero, or without a reason | Error, nothing saved |
| O | open the stock page of a listing id of another shop under Cafe Brew's URL | 404 |
| Anyone | edit or delete a line of the stock history | No such screen; the history only grows |
| M (mall admin) suspends Cafe Brew | `CAFE_WS`/stock | 403 with `This shop is not active` (reactivate afterwards from the shop applications page) |

## If something looks wrong

- Low stock notification missing: it is sent once when the quantity falls from above the alert level to at or below it, not on every sale.
- No styles: run `npm run build`.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
