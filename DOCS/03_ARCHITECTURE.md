# Architecture

Use one Laravel modular monolith with MVC, a service layer, and repositories only where data access warrants them.

```text
Request -> Route -> Middleware -> Controller -> Service -> Model / optional Repository
Response <- Blade / JSON <- Controller <- Service
```

Controllers validate/authorize and coordinate a response. Services own business workflows. Models represent persisted entities. Blade contains presentation logic. Cross-module operations use the owning module's documented services/contracts or events.

## Current folder convention

```text
app/Modules/<ExactModuleName>/
  Http/Controllers/
  Http/Requests/
  Models/
  Services/
  Repositories/
  Contracts/
  Routes/
  Providers/
  README.md
app/Core/
  Contracts/ Services/ Middleware/ DTOs/ Enums/ Exceptions/
  Patterns/Factories/ Adapters/ Proxies/ Bridges/ Facades/
```

The [module map](05_MODULE_MAP.md) supplies exact names. These folders are scaffolding; route files, module providers and application classes have not been created by the architecture task. Empty directories are not tracked by Git unless a file is added.

Preserve Laravel's existing root `app/Http`, `app/Models`, `app/Providers`, `routes`, `database`, `resources`, and `tests` conventions. A module directory does not automatically register routes, policies, events or providers. Add registration explicitly when a feature requires it, using the installed Laravel conventions.

ShopOwnerOperations and MallOwnerAdmin coordinate workflows owned by other modules; they do not create parallel product, purchase or graph entities. SharedPlatform contains shared platform capabilities; Core contains generic technical utilities. See [open ownership questions](CONFLICTS.md) before designing records absent from the ERD.

Policies, events, listeners, DTOs, enums and specialized frontend directories can be added when used. Do not implement every possible abstraction in advance. Future Blade views can follow customer/shop-owner/mall-owner areas while sharing reusable components.
