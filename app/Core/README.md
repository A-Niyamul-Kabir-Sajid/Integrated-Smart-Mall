# Core

Generic technical code shared by several modules. Core knows nothing about malls, shops, products or any other domain concept, and it never imports from `app/Modules` (an architecture test enforces this).

## What belongs here

| Folder | Contents | Example |
|---|---|---|
| `Exceptions/` | Exception types with framework-level rendering | `BusinessRuleViolation` |
| `Services/` | Stateless, domain-free utilities | `PhoneNumberNormalizer` |
| `Middleware/` | Reusable HTTP middleware with no domain rules | security headers, request IDs |
| `Modules/` | Infrastructure that loads the modules | module service provider base class |
| `Contracts/` | Interfaces for generic adapters, added with their first implementation | |

Create a folder only when the first class needs it.

## What does not belong here

- Business rules, validation of domain data, Eloquent models or policies: they live in the owning module.
- Anything used by a single module: keep it in that module until a second module needs it.
- Platform features with their own data (notifications, audit log, localization): they belong to `app/Modules/SharedPlatform`.

## Usage

```php
throw new BusinessRuleViolation('shop_unit.floor_in_other_mall', __('The floor belongs to another mall.'));

$phone = app(PhoneNumberNormalizer::class)->normalize($request->input('phone')); // '+8801712345678' or null
```
