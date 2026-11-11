# 07 Shop workspace: manage the catalogue

**Covers:** EPIC-04 (ShopOwnerOperations, ProductsCatalogue), ISM-52
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/CatalogueWorkspaceTest.php`, `tests/Feature/Modules/ProductsCatalogue/Services/CatalogueServiceTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 20 minutes

## What you are proving

Shop staff manage only their own shop's listings; shared product details can be edited only by the sole lister; other people are kept out.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.
5. Print the links: `php artisan tinker guide/TestByYourself/scripts/show-links.php`. From its **SHOPS** block keep two full URLs: the **Cafe Brew** `workspace` line and the **Fresh Mart** `workspace` line. Below they are called `CAFE_WORKSPACE` (`http://127.0.0.1:8000/workspace/shops/<cafe id>/catalogue`) and `FRESH_WORKSPACE`. From **LISTINGS** keep the full id of one **Fresh Mart** listing, `FRESH_LISTING_ID`.

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `owner@demo.test`, `staff@demo.test`, `customer@demo.test`, `platform@demo.test`.

## Steps

### 1. Reach the workspace

1. Sign in as `owner@demo.test`, open <http://127.0.0.1:8000/account>.
2. Under "Shops you work in" press **Manage catalogue** for Cafe Brew (this is `CAFE_WORKSPACE`).

**Expect:** a table with Product, SKU, Price, Availability, Listing status (Active / Archived) and Edit.

### 2. Add a brand-new product

1. Press **Add product** (`CAFE_WORKSPACE/create`), section **Create a new product**.
2. Product name `Hot Chocolate`, Category Drinks, Brand `Cafe Brew`, Description, Option `Regular`, Your SKU `HC-001`, Price (BDT) `180`, Availability **Available**, press **Create and add**.

**Expect:** back in the catalogue with the new Active listing. Open <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=hot+chocolate>: the product is found at ৳180.

### 3. Sell a product that already exists

1. **Add product**, section **Sell a product already in the mall catalogue**: choose **Mineral Water**, give it SKU `MW-CB1` and price `30`, press **Add to my catalogue**.

**Expect:** Cafe Brew now appears among Mineral Water's offers on <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=water>. You are *not* the sole lister, so you cannot edit its shared details (step 5).

### 4. Edit and archive your listing

1. Press **Edit** on Hot Chocolate, change price to `190` and availability to **Out of stock**, **Save listing**.

**Expect:** the product page shows ৳190 and "Out of stock".

2. Press **Archive listing**, then search <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=hot+chocolate>.

**Expect:** the offer is gone for customers. In the workspace the listing shows under Archived; **Restore listing** brings it back.

### 5. Shared details and photos

1. On Hot Chocolate (you are the sole lister) edit "Shared product details" and **Save product details**.

**Expect:** saved.

2. On the new Mineral Water listing (several shops sell it) open its edit page.

**Expect:** the shared details are read-only with the note that other shops also sell it, so only the mall office can change them.

3. On Hot Chocolate use **Add a photo**: pick a JPG, PNG or WebP of at most 2 MB, add a description (required), **Upload**.

**Expect:** the photo appears on the edit page. A non-image file, a larger file or a missing description is refused.

### 6. Bad input

| Try | Expect |
|---|---|
| Price `-5` or `abc` | Validation error, nothing saved |
| A SKU already used in your shop (`HC-001`) | Error |
| The same product and option listed twice by your shop | Business-rule error |

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Guest (signed out) | `CAFE_WORKSPACE` | Redirect to <http://127.0.0.1:8000/login> |
| `customer@demo.test` | `CAFE_WORKSPACE` | 403 |
| `owner@demo.test` | `FRESH_WORKSPACE` (another shop) | 403 |
| `owner@demo.test` | `CAFE_WORKSPACE/<FRESH_LISTING_ID>/edit` (a Fresh Mart listing under Cafe Brew's URL) | 404 |
| `owner@demo.test` | `CAFE_WORKSPACE/not-a-uuid/edit` | 404 |
| `staff@demo.test` | `CAFE_WORKSPACE` | Allowed (staff work in the shop) |
| `platform@demo.test` | `FRESH_WORKSPACE` | Allowed |

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
