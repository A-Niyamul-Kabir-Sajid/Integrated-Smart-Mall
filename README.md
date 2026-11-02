# Integrated Smart Mall (3 2 SyStem)

A browser-based platform for a shopping mall with three interfaces on one Laravel backend: customers (directory, product search, indoor navigation), shop owners and mall administrators. KUET CSE system-design project.

Before changing code, read [DOCS/00_START_HERE.md](DOCS/00_START_HERE.md).

## Requirements

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer (verified on 8.5.12) | Laravel 13 does not run on PHP 8.2, so XAMPP's bundled PHP is too old. [php.new](https://php.new) installs PHP and Composer per user. |
| Composer | 2.8 or newer (verified on 2.8.12) | |
| Node.js / npm | 22 / 10 (verified on 22.16.0 / 10.9.2) | Builds the Vite assets. |

Verified baseline on 2026-11-03: Laravel 13.34.0, `php artisan about` boots, `composer audit` is clean and `php artisan test` passes.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Start the application with `composer run dev` (server, queue, logs and Vite together) or `php artisan serve`.

## Checks

```bash
php artisan test          # test suite
vendor/bin/pint --test    # code style
composer audit            # dependency advisories
```

## Troubleshooting

- `Undefined array key "url"` from Composer: an older global Composer is running under PHP 8.5. Use the Composer installed by php.new (`%USERPROFILE%\.config\herd-lite\bin\composer`) or update Composer.
- `Unable to locate file in Vite manifest`: run `npm run build`, or keep `npm run dev` running.
