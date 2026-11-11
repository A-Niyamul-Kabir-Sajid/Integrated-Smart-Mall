# 10 Customer reviews and the Verified purchase badge

**Covers:** EPIC-06 + EPIC-07 (ReviewsModeration, PurchaseVerification), ISM-71, ISM-74, ISM-77
**Automated tests:** `tests/Feature/Modules/ReviewsModeration/CustomerReviewPagesTest.php`, `.../ReviewServiceTest.php`, `.../ReviewDataLayerTest.php`, `.../RatingSummaryTest.php`, `tests/Feature/Modules/PurchaseVerification/PurchaseVerifierTest.php`
**Needs:** nothing else; this file is self-contained (it issues its own purchase codes and creates its own second customer)
**Time:** about 30 minutes

## What you are proving

- A signed-in customer can review a shop or a product.
- A product review shows **Verified purchase** only when the customer redeems a real purchase code for that product; the badge can never be requested by the form.
- A code works once, only for its buyer and only for the product it was sold for.
- The shop review has no code field and never carries the badge.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...` everywhere.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `customer@demo.test`, `owner@demo.test`.

### Create the second customer

1. Open <http://127.0.0.1:8000/register>, Full name `Second Customer`, Email `second@demo.test`, Password `Passw0rdTest` twice, **Create account**, then **Sign out**.

### Issue a purchase code for Cold Coffee (Regular)

The shop-side sales screen does not exist yet, so a helper plays the seller. In PowerShell from the project folder:

```powershell
$env:PRODUCT = 'Cold Coffee'; $env:OPTION = 'Regular'
php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php
```

(Bash: `PRODUCT='Cold Coffee' OPTION=Regular php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php`.)

It prints two things; keep both:

```text
Review page : http://127.0.0.1:8000/malls/niagara-city-mall/listings/<listing id>/reviews
Purchase code (shown once): ismv_...
```

Below they are `REVIEW_PAGE` and `CODE1`. The code is for `customer@demo.test` and only for Cold Coffee (Regular). Run the script again with `$env:PRODUCT = 'Espresso'; $env:OPTION = 'Single shot'` to get `CODE2` and `ESPRESSO_PAGE` for later steps. More about the script: file 09.

## Steps

### 1. Look at the page as a guest

1. Signed out, open `REVIEW_PAGE`.

**Expect:** the rating summary, any reviews with an **All / Verified purchases** filter, and "Sign in to write a review." No form.

### 2. Sign in as the customer

1. Open <http://127.0.0.1:8000/login>, enter `customer@demo.test` and `password`.
2. Open `REVIEW_PAGE` again.

**Expect:** the "Write a review" form: star options (5 down to 1 stars), **Title (optional)**, **Your review**, **Purchase code (optional)**.

### 3. Write a verified review

1. Pick a star rating.
2. Write a body of at least 10 characters.
3. Paste `CODE1` into **Purchase code**.
4. Press **Publish review**.

**Expect:**
- The review appears on the page at once (reviews publish immediately).
- It shows a **Verified purchase** badge.
- The rating summary counts it, and it appears under the **Verified purchases** tab (`REVIEW_PAGE?verified=1`).
- <http://127.0.0.1:8000/account/reviews> lists it as Published.
- The purchase-code box is gone from the form (already verified); the form says you can edit until a date 30 days ahead.

### 4. The code cannot be reused

Each of these must be **refused** and must save **nothing**.

| # | Try | Expect |
|---|---|---|
| a | Sign out, sign in as `second@demo.test`, open `REVIEW_PAGE`, write a review (10+ characters) and paste `CODE1` | Error banner; no review is saved for that account |
| b | Sign in as `customer@demo.test`, open `ESPRESSO_PAGE`, write a review and paste `CODE1` (a code for a different product) | Error banner; no review is saved |
| c | Paste a made-up code such as `ismv_notarealcode` | Error banner; no review is saved |
| d | Paste `CODE1` with one character changed | Error banner; no review is saved |
| e | As `customer@demo.test`, withdraw the verified review (step 7), then paste `CODE1` into a new review of Cold Coffee | Refused: the code stays spent even after withdrawal |

The error looks the same whether the code is unknown, malformed or somebody else's, so an attacker cannot tell which.

### 5. A forged badge is ignored

1. As `customer@demo.test`, open `ESPRESSO_PAGE`. Press F12, find the review `<form>`, and add inside it `<input type="hidden" name="verified" value="1">`.
2. Submit a review without a code.

**Expect:** the review is published **without** a badge. The badge exists only through a redeemed code.

### 6. Edit in place, then verify later

1. Still on `ESPRESSO_PAGE` (you just wrote an unverified review), change the stars or text and press **Update review**.

**Expect:** the same review is updated (still one review per product). The page says you can edit until a date 30 days after writing.

2. Use the extra box **Bought it? Verify your purchase**, enter a wrong code, press **Verify**.

**Expect:** refused; the review is unchanged.

3. Enter `CODE2` and press **Verify**.

**Expect:** the badge appears on the existing review.

### 7. Withdraw and rewrite

1. Open <http://127.0.0.1:8000/account/reviews>, press **Withdraw** on the Cold Coffee review and confirm.

**Expect:** it disappears from the public page and from the rating summary; the list shows it as Withdrawn.

2. Open `REVIEW_PAGE` and write a new review (without a code).

**Expect:** accepted (the same record is revived; a withdrawn review can be rewritten at any time). Now do check 4e.

### 8. Validation

On `REVIEW_PAGE` as `customer@demo.test`:

| Try | Expect |
|---|---|
| No star selected | The browser stops you, or an error shows |
| Body shorter than 10 characters | Error |
| Body over 2000 characters, or title over 160 characters | Error |
| Eleven submissions within a minute | HTTP 429, wait one minute |

### 9. The shop review type

1. As `customer@demo.test` open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew/reviews>.

**Expect:**
- The same star, title and body fields, but **no purchase-code field** (product reviews only).
- After publishing, the review shows no Verified badge.
- The page says "This score is for the shop. Product reviews are rated separately on each product." Shop and product scores are never mixed.
- <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew> shows the shop rating summary, and <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=coffee> shows each offer's own rating.

### 10. Staff cannot review their own shop

1. Sign out, sign in as `owner@demo.test` and open `REVIEW_PAGE` and <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew/reviews>.

**Expect:** "You work at this shop, so you cannot review it or its products." No form.

### 11. Guests and unknown targets

| Account | Try | Expect |
|---|---|---|
| Signed out | Submit the review form (you can only see a sign-in prompt) | No form; a forged POST redirects to <http://127.0.0.1:8000/login> |
| Any | <http://127.0.0.1:8000/malls/niagara-city-mall/listings/not-a-uuid/reviews> | 404 |
| Any | <http://127.0.0.1:8000/malls/niagara-city-mall/shops/tech-galaxy/reviews> (draft shop) | 404 |

A review hidden by a moderator is covered in file 12.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
