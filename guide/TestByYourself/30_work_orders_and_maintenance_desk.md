# 30 Maintenance desk, work orders, the operations queue and moderation hand-off

**Covers:** EPIC-11 ISM-116 / ISM-117 / ISM-118 (maintenance work orders, moderation queue, audit and permissions), MallOwnerAdmin with ShopOwnerOperations and ReviewsModeration
**Automated tests:** `tests/Feature/Modules/MallOwnerAdmin/WorkOrderTest.php`, `tests/Feature/Modules/MallOwnerAdmin/OperationsAccessTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 30 minutes

## What you are proving

The mall office sees every shop's maintenance request, creates a work order for it, assigns it to an administrator and follows it to completion; the shop's request moves along with it and the shop confirms. The office can also create work orders for common areas. The home page shows what is waiting in every queue, including reported reviews, and every change is in the activity log. Shop and customer accounts reach none of it.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep the `Admin home` line as `ADMIN` and Cafe Brew's `workspace` URL as `CAFE_WS`.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| O | `owner@demo.test` | Cafe Brew owner (raises the request, confirms the fix) |
| M | `mall.admin@demo.test` | Mall administrator |
| C | `customer@demo.test` | Customer (must be refused) |

### Create the data this file needs

1. As O open `CAFE_WS`/maintenance. Title `Leaking tap`, category `Plumbing`, priority `Urgent`, details `The tap in the back room leaks.`, **Send to the mall**. Note the number `MNT-XXXXXXXX`.
2. For the moderation step: follow step 1 of [12_report_and_moderation.md](12_report_and_moderation.md) so that one review has an open report; if you skip this, step 6 shows `Reported reviews 0` and nothing to moderate.

## Steps

### 1. The queues (M)

1. Sign in as M and open `ADMIN`.

**Expect:** the `Waiting for you` section lists `Shop applications`, `Maintenance requests` (1), `Complaints`, `Open work orders` and `Reported reviews`, each with a count and a link.

### 2. The mall side of a request (M)

1. Open `ADMIN`/maintenance and open `Leaking tap`. Click **Acknowledge**.

**Expect:** status `Acknowledged`. As O the notification list holds `Maintenance update`, and `CAFE_WS`/maintenance shows `Acknowledged`.

### 3. A work order from the request (M)

1. On the request fill `Floor` Ground floor, where `Back room`, due date a few days ahead, click **Create work order**.

**Expect:** you land on the work order `WO-XXXXXXXX` (priority `Urgent`, shop Cafe Brew, linked to the request). A second work order for the same request is refused until the first is completed or cancelled.

2. Choose M as assignee, click **Assign**, then **Start work**.

**Expect:** the request now reads `Being fixed` (check as O). Starting before assigning is refused.

3. Enter `Replaced the washer.` and click **Complete**. Completing without a note is refused.

**Expect:** the work order is `Completed`; the request is `Fixed, please confirm` with `What the mall did: Replaced the washer.`; O is notified.

### 4. The shop confirms (O)

1. As O open the request and click **It is fixed, close the request**.

**Expect:** the request is `Closed`. As M the request shows the full history (acknowledged, being fixed, fixed, closed) with actors.

### 5. A common-area job (M)

1. Open `ADMIN`/work-orders, click **New work order**: title `Replace corridor light`, text `The light outside the lift flickers.`, floor Ground floor, priority `Normal`. Create it.
2. Assign it, then **Cancel work order** with the reason `Duplicate of another job.` A reason under 5 characters is refused.

**Expect:** status `Cancelled`, final; the history shows each step. **Assigned to me** on the list filters correctly.

### 6. Moderation hand-off (M)

1. If a review has open reports, `ADMIN` shows `Reported reviews` above 0. Click it: the moderation desk opens with a `Mall administration` link back to `ADMIN`. Hide the review with a reason.

**Expect:** the home count drops, and `ADMIN`/activity filtered by area `review` shows `review.moderated`.

### 7. The activity log (M)

1. Open `ADMIN`/activity.

**Expect:** `maintenance.acknowledged`, `work_order.created`, `work_order.assigned`, `work_order.in_progress`, `work_order.completed`, `work_order.cancelled`, `maintenance.resolved`, `maintenance.closed` with times, actors and notes; filter by area `work_order` to narrow it.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `ADMIN`, `ADMIN`/maintenance, `ADMIN`/work-orders | Redirect to sign in |
| O (shop owner, any staff) | the same URLs, or posting to resolve a request | 403, nothing changes |
| C (customer) | the same URLs | 403 |
| Administrator of another mall | the same URLs for this mall | 403; a request or work order of this mall under their own mall's URL gives 404 |
| M | assigning a work order to the shop owner or an administrator of another mall | Refused |
| M | completing a work order that was never started | Refused |
| M | resolving the shop's request without a note | Refused |
| Anyone | editing a work order's title or deleting it | There is no such button; created work orders are frozen |

## If something looks wrong

- 429 Too Many Requests: administration screens allow 60 requests a minute.
- A request the shop cancelled stays `Cancelled`; completing its work order still works but does not change the request.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
