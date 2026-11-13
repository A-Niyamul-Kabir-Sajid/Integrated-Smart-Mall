# 25 Customer support conversations

**Covers:** EPIC-10 ISM-107 / ISM-109 (customer support), ShopOwnerOperations
**Automated tests:** `tests/Feature/Modules/ShopOwnerOperations/SupportTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 15 minutes

## What you are proving

A signed-in customer can write to a shop; the shop's cashiers, managers and owner answer in their workspace; both sides can close the conversation; closed conversations take no more messages; a customer sees only their own conversations and a shop sees only its own. Replies to **reviews** are a different screen (guide 11).

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| C | `customer@demo.test` | Customer |
| S | `staff@demo.test` | Cafe Brew cashier |
| O | `owner@demo.test` | Cafe Brew owner |
| X | `test@example.com` | A second customer |

### Create the data this file needs

1. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep Cafe Brew's `workspace` URL as `CAFE_WS`. The support inbox is `CAFE_WS`/support.

## Steps

### 1. Write to the shop (C)

1. Sign in as C. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew>. In the **Contact the shop** box click **Contact the shop**.
2. Choose **Complaint**, subject `Wrong item`, message `I received tea instead of coffee.`, **Send message**.

**Expect:** the conversation page shows your message and the status `Waiting for the shop`. <http://127.0.0.1:8000/account/support> lists it. Signed out, the shop page offers **Sign in to contact the shop** instead.

### 2. The shop answers (S, O)

1. As O the notification list holds `New customer message`.
2. As S open `CAFE_WS`/support. The conversation is listed first (open conversations come before answered and closed).
3. Open it and reply `So sorry, bring the receipt and we will replace it.`, **Send reply**.

**Expect:** status `Answered`; as C the notification list holds `The shop replied` and the conversation shows the answer with the shop name.

### 3. The customer replies and closes (C)

1. Reply `Thank you, coming at 5.` The status returns to `Waiting for the shop`.
2. Click **Close this conversation**.

**Expect:** `This conversation is closed.`; the reply form is gone. As S, the closed conversation is listed under **Closed**, and replying is refused.

### 4. Limits

1. Send an empty message or a subject of one letter: refused with a message under the field.
2. As C start 10 conversations without closing any, then an 11th: refused (`You already have 10 open conversations`). Close one and the next works.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | <http://127.0.0.1:8000/account/support> | Redirect to sign in |
| X | the conversation page of C's thread, its reply or close | 404 |
| C | `CAFE_WS`/support | 403 |
| A catalogue editor of Cafe Brew | `CAFE_WS`/support | 403 |
| Owner of another shop | `CAFE_WS`/support, or C's thread id under their own shop URL | 403 / 404 |
| Anyone | edit or delete a message | No such screen; messages only append |
| O when the mall suspends Cafe Brew (guide 20) | `CAFE_WS`/support | 403; customers can still write to a suspended (closed) shop but nobody answers until it reopens |

## If something looks wrong

- 429 Too Many Requests: messages are limited to 10 per minute.
- The **Contact the shop** box is missing: only listed (active or suspended) shops can be contacted.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
