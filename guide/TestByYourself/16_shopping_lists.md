# 16 Shopping lists

**Covers:** EPIC-08 ISM-86 (ISM-87, ISM-88), FavouritesPreferences, decision D-012
**Automated tests:** `tests/Feature/Modules/FavouritesPreferences/Services/ShoppingListServiceTest.php`, `tests/Feature/Modules/FavouritesPreferences/Http/ShoppingListControllerTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 10 minutes

## What you are proving

A signed-in customer can keep several shopping lists, one per mall, add products from product pages and free-text items, tick items off, change quantities, rename and delete lists. A product the mall stops selling stays on the list with a "Not sold here any more" label. Nobody can see or change another account's list.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `customer@demo.test`, `staff@demo.test`, `owner@demo.test` (Cafe Brew).

### Create the data this file needs

Nothing beyond the demo seed; step 1 creates the list.

## Steps

### 1. Create a list

1. Sign in as `customer@demo.test`, open <http://127.0.0.1:8000/account> and press **Shopping lists** (or open <http://127.0.0.1:8000/account/lists>).

**Expect:** "You have no shopping lists yet." and a **New list** form whose Mall menu offers Niagara City Mall.

2. Name: `Weekend shopping`, Mall: Niagara City Mall, press **Create list**.

**Expect:** you land on the list page with "Your list "Weekend shopping" is ready." and "This list is empty".

### 2. Add products from product pages

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=mineral> and open **Mineral Water**.

**Expect:** a box "Add to a shopping list" with a Quantity field and a button **Add to "Weekend shopping"**.

2. Quantity `2`, press **Add to "Weekend shopping"**.

**Expect:** "Mineral Water was added to "Weekend shopping"."

3. Press the same button again with Quantity `3`.

**Expect:** still one Mineral Water line on the list, now with quantity **5** (repeats are merged).

4. Do the same for **Espresso** (<http://127.0.0.1:8000/malls/niagara-city-mall/products?q=espresso>), quantity 1.

### 3. Free-text items

1. On the list page (<http://127.0.0.1:8000/account/lists> → Weekend shopping), under **Add an item** type What to buy `Birthday candles`, Quantity `1`, Note `blue ones`, press **Add**.

**Expect:** the line appears without a link, with the note under it.

### 4. Tick off, change quantity, remove

1. Press the empty square in front of **Birthday candles**.

**Expect:** the square shows ✓, the text is struck through and the line moves to the bottom.

2. Press ✓ again. **Expect:** it is open again.
3. On the Mineral Water line change the number to `1`, press **Update**. **Expect:** quantity 1 after reload.
4. Press **Remove** on Birthday candles. **Expect:** "The item was removed."

### 5. A product that is no longer sold stays on the list

1. In a second browser sign in as `owner@demo.test`, open <http://127.0.0.1:8000/account> → **Manage catalogue** (Cafe Brew), open **Espresso**'s **Edit** and press **Archive listing**.
2. As the customer, reload the list page.

**Expect:** Espresso shows with a grey **Not sold here any more** label and no link; quantity and note are kept. Restore the listing as the owner afterwards (**Restore listing**).

### 6. Rename and delete

1. **Rename list** to `Weekend shopping 2`, press **Save name**. **Expect:** "The list was renamed." and the new title.
2. Press **Delete list** and confirm. **Expect:** back on the lists page with "The list was deleted."

### 7. Validation

1. On a list page press **Add** with What to buy empty. **Expect:** "Type what you want to buy.", nothing added.
2. Type `Milk`, Quantity `100`, **Add**. **Expect:** a quantity error (at most 99), nothing added.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| guest | <http://127.0.0.1:8000/account/lists> | redirected to sign in |
| `staff@demo.test` | the customer's list address (copy it from the customer's address bar, e.g. http://127.0.0.1:8000/account/lists/01...) | **404 Not Found**; the list is not listed at <http://127.0.0.1:8000/account/lists> |
| `customer@demo.test` | create a 21st list (create 20, then one more) | "You can keep at most 20 shopping lists. Delete one first." |
| `customer@demo.test` | add more than 100 lines to one list | "A list can hold at most 100 items." |

## If something looks wrong

- No "Add to a shopping list" box on a product page: sign in first; the box lists only lists for that mall.
- 429 Too Many Requests: more than 60 list or save actions in a minute; wait one minute.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
