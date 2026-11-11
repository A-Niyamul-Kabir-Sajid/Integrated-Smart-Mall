# 17 Language everywhere and the usual mall

**Covers:** EPIC-08 ISM-89 (ISM-90, ISM-91), FavouritesPreferences (D-010), SharedPlatform locale
**Automated tests:** `tests/Feature/Modules/FavouritesPreferences/PreferenceLookupTest.php`, `tests/Feature/Modules/FavouritesPreferences/Http/PreferenceControllerTest.php`, `tests/Feature/Modules/SharedPlatform/LocaleTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 8 minutes

## What you are proving

A customer who chooses Bangla sees Bangla not only on the pages but also in error messages (sign-in, registration, password reset, form validation) and in page navigation. The "usual mall" preference puts that mall first on the mall list. English visitors still see English messages.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `customer@demo.test`.

### Create the data this file needs

The demo has one mall, and with one mall the home page jumps straight into it. To see the mall list, add a second mall:

```
php artisan tinker --execute 'App\Modules\MallShopDirectory\Models\Mall::factory()->create(["name" => "Aardvark Test Mall", "slug" => "aardvark-test-mall"]);'
```

## Steps

### 1. Bangla error messages for guests

1. Signed out, open <http://127.0.0.1:8000/login> and press **বাং** in the header.
2. Email or mobile `nobody@example.com`, password `wrong-password`, press the sign-in button.

**Expect:** the error is in Bangla: "এই তথ্যের সাথে কোনো অ্যাকাউন্ট মেলেনি।"

3. Open <http://127.0.0.1:8000/register>, leave the name empty, type `not-an-email` as email (if the browser blocks it, remove `type="email"` with F12), submit.

**Expect:** "পুরো নাম দেওয়া আবশ্যক।" and "সঠিক ইমেইল ঠিকানা দিন।" (not "The display name field is required.").

4. Open <http://127.0.0.1:8000/forgot-password>, submit `nobody@example.com`.

**Expect:** a Bangla message.

### 2. Page navigation in Bangla

1. Still in Bangla, open <http://127.0.0.1:8000/malls/niagara-city-mall/shops>.

**Expect:** below the shop cards the "Showing ... results" line and the previous/next links are in Bangla.

### 3. English is unchanged

1. Press **EN**, repeat step 1.2.

**Expect:** "These credentials do not match our records." in English.

### 4. The usual mall comes first

1. Open <http://127.0.0.1:8000/> signed out.

**Expect:** "Choose a mall" lists Aardvark Test Mall first, then Niagara City Mall (alphabetical); no badge.

2. Sign in as `customer@demo.test`, open <http://127.0.0.1:8000/account/preferences>, set **Usual mall** to Niagara City Mall, press **Save preferences**.
3. Open <http://127.0.0.1:8000/>.

**Expect:** Niagara City Mall is first, with a green border and the badge **Your usual mall**.

### 5. Preferences stay with the account

1. Set **Language** to Bangla on <http://127.0.0.1:8000/account/preferences>, save, sign out, press **EN** in the header, sign in again.

**Expect:** after signing in the pages are Bangla again (the stored language is applied at sign-in).

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| guest | <http://127.0.0.1:8000/account/preferences> | redirected to sign in |
| `customer@demo.test` | F12, change the Usual mall option value to a random id, save | "বাছাই করা নিয়মিত মল সঠিক নয়।" in Bangla, "The selected default mall id is invalid." in English, nothing saved |

## If something looks wrong

- Still English messages in Bangla: the language switch is stored in the session; press **বাং** again, then retry.
- Clean up the extra mall with `php artisan migrate:fresh --seed`.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
