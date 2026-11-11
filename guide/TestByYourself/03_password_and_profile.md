# 03 Password reset, password change and profile

**Covers:** EPIC-02 (AccountsAccess)
**Automated tests:** `tests/Feature/Modules/AccountsAccess/Http/Controllers/PasswordResetControllerTest.php`, `.../PasswordControllerTest.php`, `.../ProfileControllerTest.php`, `tests/Feature/Modules/AccountsAccess/Services/AccountSecurityServiceTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 15 minutes

## What you are proving

Own details can be edited safely, the password can be changed, and a forgotten password can be reset by email without revealing whether an email exists.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.
5. Mail is written to `storage/logs/laravel.log` (`MAIL_MAILER=log`), not sent.

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

### Create the account used below

1. Open <http://127.0.0.1:8000/register>.
2. Full name `Second Customer`, Email `second@demo.test`, Mobile `01712345678`, Password `Passw0rdTest`, same confirmation, **Create account**.

You are now signed in as `second@demo.test` with password `Passw0rdTest`.

## Steps

### 1. Edit the profile

1. Open <http://127.0.0.1:8000/account/profile>.

**Expect:** the form is prefilled with your own name, email and mobile.

2. Change only the name to `Second Customer II` and save.

**Expect:** saved without asking for a password.

3. Change the email to `second2@demo.test` and save without the current password.

**Expect:** refused: changing a contact needs the current password. Enter `Passw0rdTest` and save: accepted.

4. Change the mobile number to `01812345678` (with the current password).

**Expect:** it is saved in international form (`+8801812345678`) and is no longer marked verified.

5. Try the email `customer@demo.test`.

**Expect:** refused (belongs to another account).

6. Clear both email and mobile.

**Expect:** refused: one contact must remain.

### 2. Change the password

1. Open <http://127.0.0.1:8000/account/password>.
2. Enter a wrong current password.

**Expect:** refused.

3. Enter the correct current password and a weak new one (`abc`); then a new password equal to the old one.

**Expect:** both refused.

4. Enter the current password `Passw0rdTest` and a new strong password `NewPassw0rd9` with confirmation.

**Expect:** accepted. Other sessions of this account are signed out. Sign out and sign in again at <http://127.0.0.1:8000/login> with `NewPassw0rd9`.

5. Submit the form wrongly six times within a minute.

**Expect:** HTTP 429 on the 6th.

### 3. Forgot password

1. Sign out. Open <http://127.0.0.1:8000/login> and follow **Forgot password?** (<http://127.0.0.1:8000/forgot-password>).
2. Enter `second2@demo.test` and submit.

**Expect:** a neutral confirmation message.

3. Open `storage/logs/laravel.log`, search for `reset-password/` and copy the full link (it looks like `http://127.0.0.1:8000/reset-password/<token>?email=...`).
4. Open the link and set the new password `ResetPassw0rd7`.

**Expect:** accepted, and you can sign in with it. Opening the same link again is refused (the token works once).

5. Repeat step 2 with `nobody@demo.test`.

**Expect:** exactly the same message as for a real email, and nothing new in the log.

6. Request more than 3 resets within a minute.

**Expect:** HTTP 429.

## Known limit

A phone-only account cannot reset its password by itself yet (an information-desk process is planned).

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
