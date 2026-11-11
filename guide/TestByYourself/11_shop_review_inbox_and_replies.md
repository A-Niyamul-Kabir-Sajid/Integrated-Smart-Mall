# 11 Shop review inbox and public replies

**Covers:** EPIC-07 ISM-77 (ShopOwnerOperations review workspace, ReviewsModeration replies)
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/ReviewWorkspaceTest.php`, `tests/Feature/Modules/ReviewsModeration/ReviewReplyAndQueryTest.php`
**Needs:** nothing else; this file is self-contained (it creates its own reviews)
**Time:** about 20 minutes

## What you are proving

Staff see only the published reviews of their own shop, can reply once publicly, and can edit or remove that reply. They cannot touch another shop's reviews.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.
5. Print the links: `php artisan tinker guide/TestByYourself/scripts/show-links.php`. From **SHOPS** keep the `review inbox` URL of **Cafe Brew** (`http://127.0.0.1:8000/workspace/shops/<cafe id>/reviews`, called `CAFE_INBOX`) and of **Fresh Mart** (`FRESH_INBOX`).

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `customer@demo.test`, `owner@demo.test`, `staff@demo.test`.

### Create the reviews the inbox will show

1. Sign in as `customer@demo.test`.
2. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew/reviews>, choose 4 stars, body `Friendly staff and good coffee.`, **Publish review**.
3. Issue a purchase code (the sales screen does not exist yet, so a helper plays the seller):

   ```powershell
   $env:PRODUCT = 'Espresso'; $env:OPTION = 'Single shot'
   php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php
   ```

   It prints a `Review page` URL and a `Purchase code`.
4. Open that `Review page` as the customer, choose 5 stars, body `Strong espresso, worth the price.`, paste the code, **Publish review**. It shows a **Verified purchase** badge.
5. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/fresh-mart/reviews> and publish a 3-star review `Fresh Mart is fine.` (used to prove isolation). Sign out.

## Steps

### 1. Open the inbox

1. Sign in as `owner@demo.test`, open <http://127.0.0.1:8000/account> and follow **Reviews** for Cafe Brew (this is `CAFE_INBOX`).

**Expect:** the two Cafe Brew reviews, labelled "Shop review" and by product, with the **Verified purchase** badge on the Espresso one. The Fresh Mart review is **not** here. The tabs **All** and **Not answered yet** filter the list.

### 2. Reply

1. On the shop review press **Reply publicly**, write `Thank you for visiting!`, **Post reply**.

**Expect:** the reply shows under the review in the inbox and on <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew/reviews>, labelled as the shop's reply. The tab **Not answered yet** no longer lists it.

2. Try to post a second reply to the same review.

**Expect:** refused: one public reply per review.

3. Try to post an empty reply on the Espresso review.

**Expect:** refused.

### 3. Edit and remove

1. Change the reply text and press **Save reply**.

**Expect:** the public page shows the new text.

2. Press **Remove reply**.

**Expect:** the reply disappears publicly. You can post a new one.

### 4. Staff member

1. Sign out, sign in as `staff@demo.test` and open `CAFE_INBOX`. Reply to the Espresso review.

**Expect:** allowed (staff work in the shop).

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `CAFE_INBOX` | Redirect to <http://127.0.0.1:8000/login> |
| `customer@demo.test` | `CAFE_INBOX` | 403 |
| `owner@demo.test` | `FRESH_INBOX` (another shop) | 403 |
| `owner@demo.test` | reply to the Fresh Mart review through Cafe Brew's URL: `POST CAFE_INBOX/<fresh review id>/reply` (the review id is printed under "REVIEWS THAT EXIST" by `show-links.php`; send it with a tool such as curl or by editing a form's action in F12) | 404 |
| `owner@demo.test` | reply to a withdrawn or hidden review | Refused (replies only on published reviews; hide one in file 12 first) |
| Eleven replies within a minute | | HTTP 429 |

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
