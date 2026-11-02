# Integrated Smart Mall (3 2 SyStem)

A browser-based platform for a shopping mall with three interfaces on one Laravel backend: customers (directory, product search, indoor navigation), shop owners and mall administrators. KUET CSE system-design project.

Before changing code, read [DOCS/00_START_HERE.md](DOCS/00_START_HERE.md).

## Requirements

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer (verified on 8.5.12) with `pdo_pgsql` | Laravel 13 does not run on PHP 8.2, so XAMPP's bundled PHP is too old. The official Windows build from [windows.php.net](https://windows.php.net/download) includes `pdo_pgsql` (enable it in `php.ini`); php.new's build does not. |
| Composer | 2.8 or newer (verified on 2.8.12) | |
| Node.js / npm | 22 / 10 (verified on 22.16.0 / 10.9.2) | Builds the Vite assets. |
| Docker | Docker Desktop (verified on engine 29.9.2) | Runs PostgreSQL 17 from `compose.yaml`. |

Verified baseline on 2026-11-03: Laravel 13.34.0, `php artisan about` boots, `composer audit` is clean and `php artisan test` passes.

## Local setup

```bash
composer install
cp .env.example .env          # then set DB_PASSWORD to any local value
php artisan key:generate
docker compose up -d          # PostgreSQL on 127.0.0.1:5432
php artisan migrate
npm install
npm run build
```

## Database

PostgreSQL is the project database (`DB_CONNECTION=pgsql`). The compose service reads `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` from `.env`, keeps data in the `ism-pgsql` volume and creates a separate `ism_testing` database on first start.

| Task | Command |
|---|---|
| Start / stop PostgreSQL | `docker compose up -d` / `docker compose stop` |
| Check the connection | `php artisan db:show` |
| Rebuild the schema with demo data | `php artisan migrate:fresh --seed` |
| Delete all local data, including `ism_testing` | `docker compose down -v`, then `docker compose up -d` and `php artisan migrate` |
| Open a SQL shell | `docker compose exec pgsql psql -U ism -d ism` |

`DB_PASSWORD` is read only when the volume is first created; after changing it, recreate the volume.

Start the application with `composer run dev` (server, queue, logs and Vite together) or `php artisan serve`.

## Checks

```bash
php artisan test          # test suite on in-memory SQLite (fast, default)
composer test:pgsql       # the same suite on PostgreSQL (database ism_testing)
vendor/bin/pint --test    # code style
composer audit            # dependency advisories
```

## Troubleshooting

- `Undefined array key "url"` from Composer: an older global Composer is running under PHP 8.5. Use the Composer installed by php.new (`%USERPROFILE%\.config\herd-lite\bin\composer`) or update Composer.
- `Unable to locate file in Vite manifest`: run `npm run build`, or keep `npm run dev` running.
