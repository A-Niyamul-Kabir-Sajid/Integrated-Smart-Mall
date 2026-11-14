# 29 Customer complaints to the mall

**Covers:** EPIC-11 ISM-116 / ISM-117 (complaints), MallOwnerAdmin
**Automated tests:** `tests/Feature/Modules/MallOwnerAdmin/ComplaintTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 20 minutes

## What you are proving

A customer files a complaint about the mall or one of its shops; a mall administrator assigns it to a colleague, who works it and resolves or rejects it with a note the customer can read; the customer confirms or reopens. Every step is in an append-only history, and nobody else can see the complaint.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep the `Admin home` line as `ADMIN`.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| C | `customer@demo.test` | Customer who complains |
| M | `mall.admin@demo.test` | Mall administrator |
| P | `platform@demo.test` | Platform administrator (can also work complaints) |
| O | `owner@demo.test` | Cafe Brew owner (must be refused) |

### Create the data this file needs

1. The demo database has one mall administrator. To test assignment to a colleague, register `colleague@example.test` at <http://127.0.0.1:8000/register> (password `password-12345`), then run `php artisan tinker --execute '$u=\App\Modules\AccountsAccess\Models\User::where("email","colleague@example.test")->first(); $m=\App\Modules\MallShopDirectory\Models\Mall::where("slug","niagara-city-mall")->first(); app(\App\Modules\AccountsAccess\Services\MembershipService::class)->grantMallRole($u,$m,\App\Modules\AccountsAccess\Enums\RoleCode::MallAdmin);'`. Call this account **D**.

## Steps

### 1. File a complaint (C)

1. Sign in as C, open <http://127.0.0.1:8000/account/complaints> and click **File a complaint**.
2. Shop `Cafe Brew`, category `Cleanliness`, subject `Dirty table`, text `The tables at Cafe Brew were not cleaned for an hour.` Click **Send complaint**.

**Expect:** the complaint page shows `CMP-XXXXXXXX`, status `Received`, the text and a history line `Received`. As M and D the notification list holds `New complaint CMP-XXXXXXXX`.

### 2. Refusals when filing (C)

1. Use a one-word subject (`Hi`): refused. A text under 10 characters: refused. Nothing appears in the list.
2. File five complaints in total without any being resolved, then a sixth: refused (`You already have 5 open complaints...`).

### 3. Assign and work it (M, then D)

1. Sign in as M, open `ADMIN`/complaints, open the complaint, choose D and click **Assign**.
2. Sign in as D: `ADMIN`/complaints with **Assigned to me** ticked lists it. Click **Start looking into it**.
3. Enter `The tables were cleaned and the shop was reminded.` and click **Mark resolved**. Marking it resolved without a note is refused.

**Expect:** after each step C's notification list gains an entry and the complaint page shows the new status and the note. Assigning to the shop owner O, or to an administrator of another mall, is refused (`A complaint can only be assigned to an administrator of this mall.`).

### 4. Confirm or reopen (C)

1. On the resolved complaint enter `Still dirty this morning` and click **Reopen**: the status returns to `Being looked into` and D is notified.
2. Resolve it again as D, then as C click **That solved it, close the complaint**.

**Expect:** status `Closed`; the history lists every step with the actor and notes. No buttons are left.

### 5. Reject and withdraw

1. File a new complaint (C). As M enter the reason `This is outside the mall's control.` and click **Reject**: C sees `Not accepted` and the reason; the complaint is final.
2. File another (C) and click **Withdraw this complaint** before anyone acts: status `Withdrawn`, final.

### 6. Privacy (another customer, O)

1. Register a second customer, copy the complaint URL from step 1 and open it signed in as that customer: 404. As O, open `ADMIN`/complaints: 403.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | <http://127.0.0.1:8000/account/complaints> or `ADMIN`/complaints | Redirect to sign in |
| Another customer | someone else's complaint page, withdraw or confirm | 404, nothing changes |
| C | `ADMIN`/complaints | 403 |
| O (shop owner) or administrator of another mall | `ADMIN`/complaints, assigning or rejecting | 403 |
| M | Resolve a complaint that is only `Received` (not yet started) | Refused (`A open complaint cannot become resolved.`) |
| M | Reject without a reason or with fewer than 5 characters | Refused |
| Anyone | Edit the subject or text of a filed complaint, or delete one | There is no such button; the record is frozen |

## If something looks wrong

- 429 Too Many Requests: filing and reopening are limited to 10 an hour per account.
- Notes written by administrators are visible to the customer; do not put internal remarks in them.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
