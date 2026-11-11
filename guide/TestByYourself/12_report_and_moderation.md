# 12 Reporting a review and the moderation desk

**Covers:** EPIC-07 ISM-80 (ReviewsModeration)
**Automated tests:** `tests/Feature/Modules/ReviewsModeration/ModerationPagesTest.php`, `.../ReportAndModerationServiceTest.php`, `.../RatingSummaryTest.php`
**Needs:** nothing else; this file is self-contained (it creates its own reviews and second customer)
**Time:** about 25 minutes

## What you are proving

A customer can report someone else's review; the mall administrator decides with a written reason; every decision is recorded, changes the rating summary at once, and never reveals who reported.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.
5. Print the links: `php artisan tinker guide/TestByYourself/scripts/show-links.php`. From **MALL** keep the `Moderation desk` URL (`http://127.0.0.1:8000/admin/malls/<mall id>/moderation`, called `DESK`).

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `customer@demo.test`, `mall.admin@demo.test`, `owner@demo.test`.

### Create the second customer

1. Open <http://127.0.0.1:8000/register>, Full name `Second Customer`, Email `second@demo.test`, Password `Passw0rdTest` twice, **Create account**, then **Sign out**.

### Create the reviews to moderate

1. Issue a purchase code (a helper plays the seller):

   ```powershell
   $env:PRODUCT = 'Cold Coffee'; $env:OPTION = 'Regular'
   php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php
   ```

   Keep the printed `Review page` (`REVIEW_PAGE`) and `Purchase code`.
2. Sign in as `customer@demo.test`. Open `REVIEW_PAGE`, choose 1 star, body `Terrible, do not buy this at all.`, paste the code, **Publish review**. It is a **verified** review.
3. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew/reviews>, choose 2 stars, body `Rude service and a long wait.`, **Publish review** (an unverified shop review). Sign out.

## Steps

### 1. Report a review

1. Sign in as `second@demo.test`, open `REVIEW_PAGE`.
2. Under the customer's review open **Report this review**, choose a reason (spam, abuse, conflict of interest or other), add details, press **Send report**.

**Expect:** a confirmation message.

3. Report the same review again.

**Expect:** refused: one report per person per review.

4. Also report the shop review at <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew/reviews>. Sign out.
5. Sign in as `customer@demo.test` and open your own review.

**Expect:** no **Report this review** option on your own review. Sign out.

6. Signed out, look at `REVIEW_PAGE`.

**Expect:** no report option for guests.

### 2. Open the desk

1. Sign in as `mall.admin@demo.test`, open <http://127.0.0.1:8000/account> and press **Review moderation** under "Malls you administer". (Or open `DESK`.)

**Expect:** the page **Review moderation** with queues **Reported reviews** and **Hidden reviews**, and **Moderation history**. The note says "Reporters are never shown."

2. Find the reported reviews.

**Expect:** each review with the reason and details of the report; **no reporter name or email anywhere**.

### 3. Decide

Each decision needs a **Reason for your decision**.

1. Press **Hide review** on the shop review without a reason.

**Expect:** refused.

2. Write a reason and press **Hide review**.

**Expect:**
- The review moves to **Hidden reviews** and is gone from <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew/reviews>.
- The shop's rating summary no longer counts it.
- Sign in as `customer@demo.test`: the review is on that page with "A moderator hid your review, so it cannot be changed", the form is not offered, and <http://127.0.0.1:8000/account/reviews> shows "Hidden by a moderator".
- Sign in as `owner@demo.test` and open the shop's review inbox (**Reviews** on the account page): the hidden review is not listed.
- Back on `DESK`, Moderation history has a new line with action, reason, status before and after, and the actor.

3. As `mall.admin@demo.test` press **Restore review**, give a reason.

**Expect:** the review is public again and counted again.

4. On the verified Cold Coffee review use **Dismiss reports** (reason required).

**Expect:** the reports are closed and the review stays public.

5. On the same verified review use **Remove verified badge** with a reason.

**Expect:** the review stays published but `REVIEW_PAGE` shows no badge, and the verified count in the summary drops.

### 4. Pending reviews (optional)

New reviews publish at once, so the queue normally holds none. A moderator can publish a pending one with **Publish** if a mall ever enables approval.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `DESK` | Redirect to <http://127.0.0.1:8000/login> |
| `customer@demo.test`, `owner@demo.test` | `DESK` | 403 |
| `mall.admin@demo.test` | `DESK` with a different mall id | 403/404; another mall's reviews unreachable |
| A non-admin | POST a decision (`hide`, `restore`, ...) | 403 |
| `platform@demo.test` | `DESK` | Allowed |
| Any decision without a written reason | | Refused, nothing changes |

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
