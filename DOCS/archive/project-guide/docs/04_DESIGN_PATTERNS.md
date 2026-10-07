# 04 — Design Patterns Guide

Patterns are implementation tools, not requirements. Use them only when their trigger is present.

| Pattern | Simple trigger | Good project use | Avoid when |
|---|---|---|---|
| MVC | Separate request/data/UI | Entire Laravel web app | Never replace domain services with controllers |
| Strategy | Same job, selectable algorithm | A* vs Dijkstra vs accessible-routing cost strategy | Only one stable algorithm exists |
| Factory Method | Creation choice should be centralized | Router/report/export/notification provider creation | `new` is simple and unlikely to vary |
| Singleton | Exactly one process-wide instance is truly required | Rare; immutable configuration/provider registry | Laravel container/config already provides lifecycle; do not create global mutable state |
| Adapter | External/different interface must look like ours | WebXR/AR provider, POS, payment, map import provider | You control both interfaces and can make them match directly |
| Bridge | Two dimensions vary independently | report type × export format; notification content × channel | One dimension is fixed |
| Proxy | Control/defer/cache/guard access to another object/service | RAG/LLM gateway, paid external API, remote POS/payment | Simple local call needs no interception |
| Facade | Complex subsystem needs one simple entry point | `NavigationFacade`, `MonthlyReportFacade` | It would become a god object |
| Observer | One event should trigger several reactions | purchase verified -> token + receipt + analytics + notification | A direct synchronous call is clearer |
| State | Allowed actions depend on lifecycle status | order/reservation/complaint workflow | Boolean/status checks are trivial and stable |
| Repository | Data access needs abstraction | complex cross-table queries, alternate data source, unit testing boundary | Basic Eloquent CRUD |

## Singleton guidance for Laravel
Prefer Laravel's service container singleton binding when one shared service instance is actually needed. Do **not** hand-code a classic static Singleton for ordinary services.

## Firewall clarification
A **firewall is not a GoF design pattern**. Treat firewall/WAF/network controls under security architecture. In application code use middleware, authorization policies, validation, CSRF protection, rate limiting, security headers and audit logging.

## Project-specific examples

### Routing
`RoutingStrategy` -> `AStarRouter`, `DijkstraRouter`; `RouterFactory` selects a strategy from configuration/request requirements.

### External AR / integrations
Define an internal `ARProvider` contract. `WebXRAdapter` or another provider adapter implements it. Wrap remote/paid providers with a Proxy for credentials, quotas, retries and observability.

### Reports
Use a Facade to orchestrate data collection. Use a Factory when selecting report builders and a Bridge when report semantics and output format both vary.

### Notifications
Domain events/Observer notify interested listeners. A Bridge can separate notification purpose from delivery channel if several combinations exist.
