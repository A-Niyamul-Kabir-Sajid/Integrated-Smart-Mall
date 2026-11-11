# 01 Smoke checks and platform basics

**Covers:** EPIC-00 and EPIC-01 (SharedPlatform): boot, health, security headers, request ids, language switch
**Automated tests:** `tests/Feature/Smoke/ApplicationSmokeTest.php`, `tests/Feature/Core/SecurityHeadersTest.php`, `tests/Feature/Core/AssignRequestIdTest.php`, `tests/Feature/Modules/SharedPlatform/LocaleTest.php`, `tests/Unit/Architecture/*`
**Needs:** nothing else; this file is self-contained
**Time:** about 5 minutes

## What you are proving

The app boots, answers health checks, sends security headers, and switches language.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...` for every artisan command.

## Steps

### 1. The app is up

1. Open <http://127.0.0.1:8000/up>.

**Expect:** the framework's health page, HTTP 200.

2. Open <http://127.0.0.1:8000/>.

**Expect:** with exactly one active mall you are redirected to <http://127.0.0.1:8000/malls/niagara-city-mall>.

### 2. Security headers and request id

1. Open <http://127.0.0.1:8000/>, press F12, Network tab, reload.
2. Click the first request and read the response headers.

**Expect:** `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy` are present, and every response carries an `X-Request-Id`. (Over HTTPS also `Strict-Transport-Security`.)

### 3. Language switch

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall>.
2. In the header press the language switch and choose **Bangla**.

**Expect:** the page and the pages after it show Bengali text for the customer screens. Switch back to English and it returns. Shop-owner workspace pages are English only (known limit).

### 4. Unknown page

1. Open <http://127.0.0.1:8000/this-does-not-exist>.

**Expect:** a 404 page, not a stack trace.

## Automatic checks you can run

```text
php artisan test --compact tests/Feature/Smoke
php artisan test --compact tests/Unit/Architecture
```

The architecture tests fail if a module reaches into another module's internals; run them after adding a class.

## If something looks wrong

- "Unable to locate file in Vite manifest": run `npm run build`.
- Style looks missing: new Tailwind classes need `npm run build` or a running `npm run dev`.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
