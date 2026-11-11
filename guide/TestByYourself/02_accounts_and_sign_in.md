# 02 Accounts: register, sign in, sign out

**Covers:** EPIC-02 (AccountsAccess), ISM-18 to ISM-26
**Automated tests:** `tests/Feature/Modules/AccountsAccess/Http/Controllers/RegisterControllerTest.php`, `.../SessionControllerTest.php`, `.../AccountControllerTest.php`, `.../Http/Middleware/AccessControlTest.php`, `tests/Feature/Security/RouteProtectionTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 10 minutes

## What you are proving

A visitor can create a customer account, sign in with an email or a mobile number, and sign out; protected pages send guests to sign in.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**, including accounts you registered earlier).
3. `npm run build`.
4. `php artisan serve` and keep it running.

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts used (password `password`): `customer@demo.test`, `owner@demo.test`, `mall.admin@demo.test`. This file creates `second@demo.test` and a phone-only account itself.

## Steps

### 1. Register with an email

1. Open <http://127.0.0.1:8000/register>.
2. Full name `Second Customer`, Email `second@demo.test`, leave Mobile empty, Password `Passw0rdTest`, Confirm password the same.
3. Press **Create account**.

**Expect:** you land on <http://127.0.0.1:8000/account>, already signed in, showing your name and email.

### 2. Register with only a mobile number

1. Press **Sign out**, then open <http://127.0.0.1:8000/register>.
2. Name `Phone Customer`, leave Email empty, Mobile `01712345678`, password `Passw0rdTest` twice.

**Expect:** the account is created. Open <http://127.0.0.1:8000/account/profile>: the number is stored in international form (`+8801712345678`).

### 3. Registration is refused when it should be

Sign out first, then try each at <http://127.0.0.1:8000/register>:

| Try | Expect |
|---|---|
| Email and mobile both empty | Error: at least one is required |
| Email `SECOND@demo.test` (same address, other letter case) | Error: already registered |
| Mobile `01712345678` again | Error: already registered |
| Mobile `123` | Error: invalid number |
| Password `short`, or a mismatching confirmation | Error on the password (needs 8+ characters with letters and numbers) |
| An 11th registration within an hour from your IP | HTTP 429 Too Many Requests |

### 4. Sign in

1. Sign out, open <http://127.0.0.1:8000/login>.
2. Email `CUSTOMER@demo.test` (capital letters on purpose), password `password`, **Sign in**.

**Expect:** signed in; <http://127.0.0.1:8000/account> shows Demo Customer. Email is case-insensitive.

3. Sign out, sign in again with Email or mobile `01712345678` and password `Passw0rdTest`.

**Expect:** signed in as Phone Customer.

### 5. Wrong credentials

1. At <http://127.0.0.1:8000/login> use `customer@demo.test` and a wrong password.

**Expect:** one generic message (it does not say whether the email exists) and the login field keeps what you typed.

2. Repeat until the 6th attempt within a minute.

**Expect:** HTTP 429. Wait a minute.

### 6. Return to the page you asked for

1. While signed out open <http://127.0.0.1:8000/account/reviews>.

**Expect:** redirect to <http://127.0.0.1:8000/login>. After signing in as `customer@demo.test` you arrive at `/account/reviews`, not the default account page.

### 7. Keep me signed in

1. At <http://127.0.0.1:8000/login> tick **Keep me signed in** and sign in.

**Expect:** the browser stores a long-lived "remember" cookie (F12, Application, Cookies).

### 8. Sign out

1. Press **Sign out**.

**Expect:** you return to <http://127.0.0.1:8000/> with "You have been signed out." Opening <http://127.0.0.1:8000/account> redirects to `/login`.

### 9. A suspended account is signed out

1. Sign in as `second@demo.test` in one browser window.
2. In a terminal open a SQL shell: `docker compose exec pgsql psql -U ism -d ism`, then run `update users set status = 'suspended' where email = 'second@demo.test';`
3. Reload <http://127.0.0.1:8000/account> in the browser.

**Expect:** you are signed out on that request. Restore it with `update users set status = 'active' where email = 'second@demo.test';` (or run `migrate:fresh --seed`).

## What each role sees on its account page

Sign in as each account at <http://127.0.0.1:8000/login> and open <http://127.0.0.1:8000/account>:

| Account | Extra sections on the page |
|---|---|
| `customer@demo.test` | Your reviews, Preferences |
| `owner@demo.test` | "Shops you work in" with **Manage catalogue** and **Reviews** |
| `mall.admin@demo.test` | "Malls you administer" with **Review moderation** |

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
