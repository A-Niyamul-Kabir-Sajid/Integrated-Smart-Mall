# 04 Preferences and language

**Covers:** EPIC-02 (FavouritesPreferences, D-010), SharedPlatform locale
**Automated tests:** `tests/Feature/Modules/FavouritesPreferences/Http/PreferenceControllerTest.php`, `.../PreferenceIntegrationTest.php`, `.../Services/PreferenceServiceTest.php`, `tests/Feature/Modules/SharedPlatform/NotificationPreferencesTest.php`, `.../LocaleTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 5 minutes

## What you are proving

A signed-in customer keeps their own language, usual mall, route mode and notification switches; guests cannot reach the page.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Account used: `customer@demo.test`, password `password`, signed in at <http://127.0.0.1:8000/login>.

## Steps

### 1. Guests are redirected

1. Signed out, open <http://127.0.0.1:8000/account/preferences>.

**Expect:** redirect to <http://127.0.0.1:8000/login>.

### 2. See the defaults

1. Sign in as `customer@demo.test`, open <http://127.0.0.1:8000/account/preferences>.

**Expect:** sections "Language and mall", "Navigation" and "Notifications and privacy"; the mall list shows active malls only (Niagara City Mall).

### 3. Save and see it applied

1. Set Language to Bangla, Usual mall to Niagara City Mall, route mode to **Step-free (avoid stairs)**, untick **Text me updates**, press **Save preferences**.

**Expect:** the page redisplays in Bengali, and the saved values are shown after reload.

2. Sign out and in again, then open <http://127.0.0.1:8000/malls/niagara-city-mall>.

**Expect:** the language stays Bangla (it is stored with the account).

### 4. Unticked boxes really turn the switch off

1. Untick every notification box and save.

**Expect:** after reload they stay unticked (not reset to default). Account-security messages (password changes) are always emailed, as the page says.

### 5. Invalid choices

1. In developer tools (F12) change a `<select>` value to something not offered (for example language `xx`) and submit.

**Expect:** refused, nothing saved.

### 6. Only your own preferences

The page has no user id field, so there is nothing to tamper with in the form. Sign in as `owner@demo.test` and check that <http://127.0.0.1:8000/account/preferences> shows the owner's own defaults, not the customer's Bangla settings.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
