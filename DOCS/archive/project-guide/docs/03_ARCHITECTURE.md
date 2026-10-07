# 03 — Architecture

## Chosen architecture

**Laravel modular monolith + MVC + Service Layer + PostgreSQL**. Use JavaScript/TypeScript only where the browser experience requires it (map rendering, WebAR/WebXR, dynamic navigation UI).

```text
Browser
  -> Route
  -> Middleware (auth / RBAC / rate limits / CSRF)
  -> Controller
  -> Application Service / Facade
  -> Domain logic / Strategy / State / policies
  -> Eloquent Model / Repository when justified
  -> PostgreSQL
  -> Controller
  -> Blade/JSON response
```

## Why this fits the project

- One deployable application is easier for a university team to understand and operate than microservices.
- Module boundaries keep the large feature set controllable.
- MVC makes request, domain and presentation responsibilities clear.
- Service classes prevent controllers from becoming business-logic containers.
- Patterns can be inserted locally without changing the entire architecture.

## Recommended source layout

```text
app/Modules/<Module>/
  Http/Controllers/
  Http/Requests/
  Models/
  Services/
  Policies/
  Repositories/       # only if justified
  Contracts/
  Events/Listeners/
resources/views/{customer,shop-owner,mall-owner}/
resources/js/{navigation,webar,map}/
routes/
database/
tests/
```

## Controller rule
A controller should normally authorize/validate, call one application service/facade, and return a response. Pathfinding, purchase verification, report composition, moderation, or recommendation logic does not belong in controllers.

## Navigation example

```text
NavigationController
  -> NavigationFacade
       -> CheckpointService
       -> RoutingService
            -> RoutingStrategy
                 -> AStarRouter
                 -> DijkstraRouter
       -> ARGuidanceService
            -> WebXRAdapter
            -> ProviderProxy (when external)
```
