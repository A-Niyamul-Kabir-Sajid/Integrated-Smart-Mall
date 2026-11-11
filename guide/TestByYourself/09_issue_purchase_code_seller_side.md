# 09 Seller side: record a sale and issue a purchase code

**Covers:** EPIC-06 (PurchaseVerification), ISM-66, ISM-68, ISM-71
**Automated tests:** `tests/Feature/Modules/PurchaseVerification/PurchaseServiceTest.php`, `.../VerificationCredentialServiceTest.php`, `.../PurchaseVerifierTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 10 minutes

## Why this file exists

A purchase code is what lets a customer's review carry the **Verified purchase** badge. In the finished app the shop prints it (QR or text) on the receipt from a sales screen. That screen is not built yet (EPIC-10), so until it is, you play the seller with a helper script that calls the same services the screen will call: `PurchaseService::recordPurchase` and `VerificationCredentialService::issueCredential`.

What happens behind the scenes:

1. The owner (`owner@demo.test`) records a completed sale of one listing to the buyer (`customer@demo.test`).
2. The system creates a one-time credential for that purchase line from 256 random bits. Only a digest is stored; the plain code exists once, in the script's output. Expiry is 90 days.
3. The customer later pastes the code into the review form (see file 10). Redeeming it ties the review to that sale.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (needed only for the last check).

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...` everywhere below.

Environment variables below are set per terminal. PowerShell: `$env:NAME = 'value'`. Bash: `NAME=value command`.

## Steps

### 1. List the listings you may sell

```text
php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php
```

**Expect:** the Cafe Brew listings with their ids: Cold Coffee (Regular), Cold Coffee (Large), Espresso (Single shot), Butter Croissant (One piece).

### 2. Sell one and get the code

By product name (no id copying):

```powershell
$env:PRODUCT = 'Cold Coffee'; $env:OPTION = 'Regular'
php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php
```

Or by id: `$env:LISTING_ID = '<id from step 1>'`.

**Expect:** output like

```text
Sale recorded: receipt MANUAL-20261110082637 for customer@demo.test at Cafe Brew
Review page : http://127.0.0.1:8000/malls/niagara-city-mall/listings/<id>/reviews
Purchase code (shown once): ismv_...
```

Copy the code at once. It cannot be shown again. Run the script again for another sale and you get another code.

Optional variables: `BUYER_EMAIL` (default `customer@demo.test`), `STAFF_EMAIL` (default `owner@demo.test`), `QUANTITY` (default 1), `BASE_URL` (default `http://127.0.0.1:8000`). Clear them afterwards: `Remove-Item Env:PRODUCT, Env:OPTION`.

### 3. Prove only authorised staff can sell

```powershell
$env:PRODUCT = 'Cold Coffee'; $env:OPTION = 'Regular'; $env:STAFF_EMAIL = 'customer@demo.test'
php artisan tinker guide/TestByYourself/scripts/issue-purchase-code.php
```

**Expect:** an authorization error ("This action is unauthorized"), and no sale recorded. Clear `STAFF_EMAIL` afterwards.

### 4. See the page the customer will use

1. Open the `Review page` URL printed in step 2 in the browser (it starts with `http://127.0.0.1:8000/malls/niagara-city-mall/listings/`).

**Expect:** the product's review page with a Sign in prompt for guests. File 10 shows how the customer uses the code.

## Rules proven by the automatic tests

| Rule | Where proven |
|---|---|
| Only staff of the shop can record a sale or issue a code | `PurchaseServiceTest`, `VerificationCredentialServiceTest` |
| Only an active shop can record sales | `PurchaseServiceTest` |
| A receipt reference is unique per shop | `PurchaseServiceTest` |
| A recorded sale cannot be edited, only voided or refunded | `PurchaseServiceTest` |
| One credential per purchase line; reissue replaces the secret; revoke stops it | `VerificationCredentialServiceTest` |
| The secret never reaches logs, audit metadata or serialisation | `VerificationCredentialServiceTest` |
| Wrong buyer, wrong listing, expired, revoked, voided sale or reused code are all refused with the same answer | `PurchaseVerifierTest` |

## Notes

- The script writes real rows to whichever database `.env` points to. It is for the demo data only.
- After `migrate:fresh --seed` all earlier codes are gone.
- PostgreSQL CHECK constraints on purchases are written but were never run (Docker was off): run `composer test:pgsql` before trusting them.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
