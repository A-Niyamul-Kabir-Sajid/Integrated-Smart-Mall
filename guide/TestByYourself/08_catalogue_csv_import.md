# 08 Catalogue CSV import

**Covers:** EPIC-04 ISM-53 (ProductsCatalogue import, ShopOwnerOperations screen)
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/CatalogueImportTest.php`
**Needs:** nothing else; this file is self-contained. The three CSV files are in [samples/](samples/): [good.csv](samples/good.csv), [bad.csv](samples/bad.csv), [wrongheader.csv](samples/wrongheader.csv).
**Time:** about 15 minutes

## What you are proving

A shop can bulk-load products from a CSV file with a preview; a file with any error saves nothing; confirming imports everything at once. Only staff of that shop may use it. This is a **shop-staff** feature; customers cannot open it.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.
5. Print the links: `php artisan tinker guide/TestByYourself/scripts/show-links.php`. From the **SHOPS** block keep the full `CSV import` URL of **Cafe Brew** (`http://127.0.0.1:8000/workspace/shops/<cafe id>/catalogue/import`, called `CAFE_IMPORT` below) and of **Fresh Mart** (`FRESH_IMPORT`).

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `owner@demo.test`, `staff@demo.test`, `customer@demo.test`.

## The files

All three live in `guide/TestByYourself/samples/`. The header line must be exactly:

```text
name,brand,category,option,option_code,sku,price,availability
```

`good.csv`:

```text
name,brand,category,option,option_code,sku,price,availability
Iced Latte,Cafe Brew,drinks,Regular,regular,IL-001,210,available
Iced Latte,Cafe Brew,drinks,Large,large,IL-002,260,available
Chocolate Muffin,,snacks-meals,One piece,default,CM-001,140,unknown
```

`bad.csv` (negative price and bad SKU characters, an unknown category, a bad availability):

```text
name,brand,category,option,option_code,sku,price,availability
Tea,,drinks,Cup,cup,T 1,-5,available
Biscuit,,no-such-category,Pack,pack,B-1,30,available
Cake,,snacks-meals,Slice,slice,C-1,60,maybe
```

`wrongheader.csv`: first line `name,price`, then `Tea,10`.

## Steps

### 1. Reach the import page

1. Sign in as `owner@demo.test` at <http://127.0.0.1:8000/login>.
2. Open <http://127.0.0.1:8000/account>, press **Manage catalogue** for Cafe Brew, then **Import CSV** at the top of the catalogue page. (Or open `CAFE_IMPORT` directly.)

**Expect:** the page "Import products from a CSV file" with a file box and **Check file**.

### 2. Preview and import a good file

1. Choose `samples/good.csv`, press **Check file**.

**Expect:** a preview table (Row, Action, Product, SKU, Price) with no errors. Nothing is saved yet: check the catalogue in another tab.

2. Press **Import these products**.

**Expect:** the three rows appear in the catalogue, and <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=latte> and `?q=muffin` find them for customers.

3. Press the browser back button and confirm again.

**Expect:** refused; nothing is duplicated (the preview token works once).

### 3. A bad file saves nothing

1. Go to `CAFE_IMPORT`, upload `samples/bad.csv`, **Check file**.

**Expect:** each bad row is listed with its row number and reason (Row 2, 3, 4), the confirm button is absent, and the catalogue is unchanged.

### 4. Header, size and re-import rules

| Try | Expect |
|---|---|
| `samples/wrongheader.csv` | "The header must be: name,brand,category,..." |
| A file of more than 500 data rows | Refused as too large |
| A non-CSV file (for example a .png) | Refused |
| `samples/good.csv` again after step 2 | Rows rejected: "Your shop already uses the SKU ..."; nothing duplicated |

### 5. Who may use it

| Account | Try | Expect |
|---|---|---|
| Signed out | `CAFE_IMPORT` | Redirect to <http://127.0.0.1:8000/login> |
| `customer@demo.test` | `CAFE_IMPORT` | 403 |
| `staff@demo.test` | `CAFE_IMPORT` | Allowed |
| `owner@demo.test` | `FRESH_IMPORT` (another shop) | 403 |

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
