# Design patterns and placement

All entries below are **planned options**, not implemented classes. Use a pattern only when its trigger exists.

| Pattern | Location when justified | Trigger |
|---|---|---|
| Strategy | MapsNavigation contracts and Services/Routing | Interchangeable route algorithms/cost policies |
| Factory | Owning module; Core/Patterns/Factories only when generic | Select/create one of several implementations |
| Adapter | Owning integration module; generic adapters in Core/Patterns/Adapters | Hide an external interface behind an internal contract |
| Proxy | Owning module; generic proxies in Core/Patterns/Proxies | Real caching, access control or remote-call isolation |
| Facade | Owning module; generic facades in Core/Patterns/Facades | Provide a simple entry to a complex subsystem |
| Bridge | Owning module; generic bridges in Core/Patterns/Bridges | Two independently varying dimensions, such as report and exporter |
| Observer / Event | Owning module Events and consuming module Listeners | Independent reactions using Laravel events |
| State | Owning module | Lifecycle behavior has outgrown enum/status handling |
| Singleton | Laravel container binding in an appropriate provider | Shared stateless object; avoid mutable request/user context in process-wide instances |
| Repository | Owning module Repositories, optional contracts/implementations | Complex repeated queries or multiple data sources |

Simple CRUD can use Controller -> Service -> Model. Strategy, State and Events stay with the owning domain. Factory selection is not automatically the GoF Factory Method pattern. Laravel facades and a subsystem facade are separate concepts; a subsystem facade need not extend Laravel's facade base class.

Firewall/WAF is infrastructure security, not a GoF pattern. Application security uses [middleware, policies and validation](08_SECURITY.md).
