# <NN> <Feature area>

**Covers:** <epic / Jira stories, module name>
**Automated tests:** <paths under tests/ that cover the same behaviour>
**Needs:** nothing else; this file is self-contained
**Time:** <about N minutes>

## What you are proving

<One or two sentences: the behaviour a person should be able to see working.>

## Setup

Every file repeats its own setup so it can be run alone. Copy this block and trim what you do not need.

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. If the steps need ids: `php artisan tinker guide/TestByYourself/scripts/show-links.php` and name the lines you keep (for example `CAFE_WORKSPACE`).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: <only the accounts this file uses>.

### Create the data this file needs

<Everything the steps depend on, created here: extra accounts through /register, purchase codes through scripts/issue-purchase-code.php, reviews written in the browser. Never "do file NN first".>

## Steps

### 1. <Short action title>

1. <Exact action with a FULL URL such as http://127.0.0.1:8000/..., the account, the field and the value>
2. <Next action>

**Expect:** <what you see, including what must NOT happen>

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| <who> | <full URL or action> | <refusal, and nothing saved> |

## If something looks wrong

- <Likely cause and fix, e.g. "429 Too Many Requests: wait one minute">

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
