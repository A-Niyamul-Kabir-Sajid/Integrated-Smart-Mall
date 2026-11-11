# 06 Customer product search, product page and comparison

**Covers:** EPIC-04 (ProductsCatalogue), ISM-38, ISM-55, ISM-56
**Automated tests:** `tests/Feature/Modules/ProductsCatalogue/Http/CustomerProductPagesTest.php`, `.../Services/ProductDiscoveryServiceTest.php`, `.../Services/CatalogueServiceTest.php`, `.../Models/CatalogueSchemaTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 15 minutes

## What you are proving

A customer can find a product, see which shops sell it and at what price, and compare up to four offers. Only products on sale in active, listed shops of this mall appear, and an archived listing disappears.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.
5. Print the links: `php artisan tinker guide/TestByYourself/scripts/show-links.php`. In its **PRODUCT PAGES** block copy the full line for **Mineral Water**; it is the product page (`http://127.0.0.1:8000/malls/niagara-city-mall/products/<id>`). The **Cafe Brew** workspace line is used in step 4.

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`. No sign-in is needed except in step 4 (`owner@demo.test`, password `password`).

## Steps

### 1. Search

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/products> (**Products** in the header).
2. Search `water` (or open <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=water>).

**Expect:** Mineral Water appears with "from ৳20" (the cheapest offer). The term stays in the box.

3. Use the filters: **Category** Drinks, **Min ৳** 25, **Max ৳** 60, tick **In stock only**, then change **Sort by** (Lowest price, Highest price, Name A–Z).

**Expect:** results and order change; filters stay selected.

4. Open <http://127.0.0.1:8000/malls/niagara-city-mall/products?min_price=100&max_price=10> (min above max) and <http://127.0.0.1:8000/malls/niagara-city-mall/products?sort=banana>.

**Expect:** both refused with a validation message.

### 2. Product page with offers

1. Open the Mineral Water product page from the links printed in Setup.

**Expect:** offers listed cheapest first (Fresh Mart ৳20, Pharma Care ৳20, Juice Corner ৳25, Food Court ৳30 for the 500 ml option), each with the shop's floor and unit, an availability badge (Available / Out of stock / Availability unknown) and the note "Prices and availability are maintained by each shop and may change."
2. Each offer has a **Reviews** link that opens that listing's review page.

### 3. Compare offers

1. On the Mineral Water page tick two or three offers and press **Compare selected (up to 4)**.

**Expect:** a side-by-side table of shop, price, availability and location.

2. Press the compare button with nothing ticked.

**Expect:** you return to the product page with "Tick at least one shop to compare."

3. Tick five or more offers (Mineral Water has five offers across its options).

**Expect:** a message that you can compare up to four, and the ticks are kept.

### 4. An archived listing disappears

1. Open <http://127.0.0.1:8000/login>, sign in as `owner@demo.test` / `password`.
2. Open the Cafe Brew workspace link from the printed links (`http://127.0.0.1:8000/workspace/shops/<cafe-brew id>/catalogue`).
3. Press **Edit** on **Espresso**, then **Archive listing**.
4. Open <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=espresso>.

**Expect:** Espresso is no longer listed. Go back to the workspace, open the Archived tab/filter, press **Restore listing**; the product returns to the search.

### 5. What must not show

| Try | Expect |
|---|---|
| A product only sold by closed Sports Zone or draft Tech Galaxy (e.g. Running Shoes EU 42, sold by Sports Zone only) | Not listed in search; its product page is not found |
| <http://127.0.0.1:8000/malls/niagara-city-mall/products/not-a-uuid> | 404 |
| A product id from another mall | 404 (no second mall in the demo data; covered by the automated tests) |

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
