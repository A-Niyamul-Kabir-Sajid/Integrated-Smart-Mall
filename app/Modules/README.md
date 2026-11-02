# Application modules

The application is a modular monolith: one Laravel app, thirteen domain modules under `app/Modules`. The names and responsibilities come from [DOCS/05_MODULE_MAP.md](../../DOCS/05_MODULE_MAP.md); do not add, rename or merge modules without updating that map.

## Namespaces

`composer.json` maps `App\` to `app/`, so a class in `app/Modules/<Module>/<Folder>/<Class>.php` lives in namespace `App\Modules\<Module>\<Folder>` with the class name equal to the file name. The architecture tests fail when a file's namespace does not match its path.

## Folder layout

Create a folder only when the module needs it.

```text
app/Modules/<Module>/
  Contracts/          Interfaces other modules may depend on
  Events/             Domain events this module dispatches (past tense: ShopStatusChanged)
  Http/Controllers/   Thin controllers: validate, authorize, call a service, return a response
  Http/Middleware/    Middleware specific to this module
  Http/Requests/      Form Requests
  Listeners/          Reactions to other modules' events
  Models/             Eloquent models for the tables this module owns
  Notifications/      Notifications sent by this module
  Policies/           Authorization policies for this module's models
  Providers/          The module's service provider (route loading, bindings, listeners)
  Routes/             web.php / api.php for this module's endpoints
  Services/           Business workflows
  README.md           Ownership summary and status
```

Kept at the Laravel defaults rather than inside modules:

| Asset | Location |
|---|---|
| Migrations | `database/migrations` (one ordered history for the shared database) |
| Factories and seeders | `database/factories`, `database/seeders` |
| Views | `resources/views/<area>` (`customer`, `shop-owner`, `mall-admin`, shared `components`) |
| Tests | `tests/Feature/Modules/<Module>`, `tests/Unit/...` |
| Generic technical code | `app/Core` (no domain rules) |

## Coupling rules

- A module owns the tables listed for it in the ERD table ownership; only its services write them.
- Other modules call its `Contracts` or `Services`, react to its `Events`, or read its models through relationships; they never call its controllers or write its tables.
- `app/Core` never depends on `app/Modules`.
