# 15 Favourites (saved shops and offers)

**Covers:** EPIC-08 ISM-83 (ISM-84, ISM-85), FavouritesPreferences
**Automated tests:** `tests/Feature/Modules/FavouritesPreferences/Services/FavouriteServiceTest.php`, `tests/Feature/Modules/FavouritesPreferences/Http/FavouriteControllerTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 8 minutes

## What you are proving

A signed-in customer can save shops and individual shop offers, sees them on one private "Saved" page, can remove them, and keeps saved items that later disappear (marked "No longer available"). Saving twice never creates duplicates, and nobody sees another account's saved items.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `customer@demo.test`, `owner@demo.test` (Cafe Brew), `staff@demo.test`.

### Create the data this file needs

Nothing beyond the demo seed. The steps use the shop **Cafe Brew** and the product **Mineral Water**.

## Steps

### 1. Guests are asked to sign in

1. Signed out, open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew>.

**Expect:** a "Sign in to save" link next to the open/closed badge, no save button.

2. Open <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=mineral> and open **Mineral Water**.

**Expect:** under the offers, "Sign in to save offers"; no Save buttons.

3. Open <http://127.0.0.1:8000/account/saved>.

**Expect:** redirect to the sign-in page.

### 2. Save a shop

1. Sign in as `customer@demo.test` and open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew>.
2. Press **Save shop**.

**Expect:** green message "Cafe Brew was saved."; the button now reads **Saved ✓**.

3. Reload the page and press **Saved ✓** again.

**Expect:** message "Removed from your saved items."; the button reads **Save shop** again. Press **Save shop** once more so it stays saved.

### 3. Save two offers

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=mineral> and open **Mineral Water**.
2. Press **Save** on the **Fresh Mart** row (500 ml bottle) and then on the **Pharma Care** row.

**Expect:** each press shows "Mineral Water was saved." and that row's button turns into **Saved ✓**. The comparison checkboxes and "Compare selected" still work (tick two rows and compare).

### 4. The Saved page

1. Open <http://127.0.0.1:8000/account/saved> (or **Saved** on <http://127.0.0.1:8000/account>).

**Expect:** under **Shops**: Cafe Brew with "Niagara City Mall · Food & Drinks". Under **Offers**: Mineral Water twice, one with "500 ml bottle · Fresh Mart" and one with "Pharma Care", each with its price. The names link to the shop and product pages.

### 5. Saving twice keeps one entry

1. Go back to Cafe Brew's page and, with developer tools (F12 → Console), run:
   `document.querySelector('[data-integration=favourites] button').closest('form').submit()` twice quickly (or press Saved ✓, then Save shop, then Save shop on two tabs).

**Expect:** <http://127.0.0.1:8000/account/saved> still lists Cafe Brew **once**.

### 6. A saved offer that disappears is kept and marked

1. In a second browser (or private window) sign in as `owner@demo.test` and open <http://127.0.0.1:8000/account>, press **Manage catalogue** for Cafe Brew.
2. Note any Cafe Brew listing, e.g. **Espresso**. As the customer, save it from its product page (<http://127.0.0.1:8000/malls/niagara-city-mall/products?q=espresso> → Espresso → **Save**).
3. As the owner, open that listing's **Edit** page and press **Archive listing**.
4. As the customer, reload <http://127.0.0.1:8000/account/saved>.

**Expect:** Espresso is still listed with a grey **No longer available** label, without a link and without a price. **Remove** deletes it.

5. As the owner, press **Restore listing** to leave the demo data as it was.

### 7. Remove from the Saved page

1. Press **Remove** next to one Mineral Water offer.

**Expect:** "Removed from your saved items."; only the other offer remains.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| guest | <http://127.0.0.1:8000/account/saved> | redirected to sign in |
| `staff@demo.test` | <http://127.0.0.1:8000/account/saved> after the customer saved items | "No saved shops yet" and "No saved offers yet": the customer's items are invisible |
| `customer@demo.test` | save a draft shop: in the console run `fetch('/favourites/shops/' + '<id of Tech Galaxy from scripts/show-links.php>', {method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('[name=_token]').value}}).then(r => r.status)` | `404`, nothing saved |
| `customer@demo.test` | <http://127.0.0.1:8000/favourites/shops/not-a-uuid> with POST (same console trick) | `404` |

## If something looks wrong

- 429 Too Many Requests: more than 60 save/remove presses in a minute; wait one minute.
- No Save buttons while signed in: run `npm run build` again and hard-reload (Ctrl+F5).

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
