# 28 Announcements and events

**Covers:** EPIC-11 ISM-116 / ISM-117 (announcements and events), MallOwnerAdmin
**Automated tests:** `tests/Feature/Modules/MallOwnerAdmin/AnnouncementTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 15 minutes

## What you are proving

The mall office writes notices and events as drafts, publishes them, takes them back or archives them. Customers (signed in or not) see only published announcements that are inside their time window, on the mall page and on the news page. Only the mall's administrators can write them.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep the `Admin home` line as `ADMIN` (it ends in the mall id).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| M | `mall.admin@demo.test` | Mall administrator |
| O | `owner@demo.test` | Cafe Brew owner (must be refused) |

Public pages (no account needed): <http://127.0.0.1:8000/malls/niagara-city-mall> and <http://127.0.0.1:8000/malls/niagara-city-mall/news>.

## Steps

### 1. A draft is invisible (M)

1. Sign in as M, open `ADMIN`/announcements and click **Write an announcement**.
2. Kind `Notice`, title `Parking lot B closed`, text `Lot B is closed on Friday for repainting.`, click **Save as draft**.

**Expect:** back on the list with `The draft was saved.`; the row shows `Notice · Draft`. Open <http://127.0.0.1:8000/malls/niagara-city-mall/news> in a private window: the notice is **not** there.

### 2. Publish (M)

1. On the list click **Publish** next to the notice.

**Expect:** `The announcement is published.`; the news page and the `News and events` panel on <http://127.0.0.1:8000/malls/niagara-city-mall> now show it.

### 3. An event (M)

1. Write another announcement: kind `Event`, title `Eid fair`, text `Music, food and crafts.`, starts a few days from now (mall time), where `Atrium, ground floor`. Click **Publish now**.

**Expect:** the news page lists it under `Events` with the day, the time and the place; with no end time it runs for 3 hours. An event saved **without a start time** is refused (`An event needs a start time.`); an end before the start is refused (`The end time must be after the start time.`); nothing is saved in either case.

### 4. Time windows (M)

1. Write a notice `Winter sale` that starts tomorrow and publish it: it is **not** visible yet (a notice waits for its start time).
2. Edit a published notice and set its end to a time in the past, then try to publish a draft with an end in the past: refused (`This announcement has already ended...`).

### 5. Take back, archive (M)

1. **Unpublish** the first notice: it disappears from the public pages and shows as `Draft`; edit its text and **Save**.
2. **Archive** it: it disappears for good, loses its Edit link and cannot be published again (`A archived announcement cannot become published.`).

### 6. Access (O)

1. Sign out, sign in as O and open `ADMIN`/announcements and `ADMIN`/announcements/create.

**Expect:** 403 on both. Signed out, both redirect to the sign-in page.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `ADMIN`/announcements | Redirect to sign in |
| O (shop owner) or any customer | `ADMIN`/announcements, posting a new one | 403, nothing saved |
| Administrator of another mall | the same URLs | 403; an announcement id of this mall under their own mall's URL gives 404 |
| M | an empty title, a text under 5 characters, an invalid kind | Error shown, nothing saved |
| Anyone | deleting an announcement | There is no delete button; announcements are only archived |

## If something looks wrong

- Times are entered and shown in the mall's time zone (Asia/Dhaka for the demo mall); the database stores UTC.
- The panel on the mall page shows at most three items; the news page shows all of them.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
