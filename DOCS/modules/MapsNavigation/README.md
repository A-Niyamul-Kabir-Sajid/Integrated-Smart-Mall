# MapsNavigation

## Responsibility

Versioned maps, checkpoints, graph routing and AR coordination. Source folder: [app/Modules/MapsNavigation](../../../app/Modules/MapsNavigation/).

Status: the map, its navigation graph, QR checkpoints, routes and the embedded map live in the separate map service (decisions D-020 and D-021); this module keeps no tables. Maps are prepared offline in Blender ([tools/blender/README.md](../../../tools/blender/README.md), decision D-019; how to scan: [tools/blender/SCANNING.md](../../../tools/blender/SCANNING.md)) and imported into [map-service/](../../../map-service/README.md), whose public API documentation is at `/docs` on the service.

This module: `Contracts/MapServiceGateway` (maps, package, annotations, `mall()`, `checkpoint()`, `route()`, `embedLink()`; adapter `Adapters/HttpMapServiceGateway`, configuration `services.map_service`, check command `php artisan maps:service-check`) and `Http/Controllers/MallMapController` with the public pages `/malls/{mall}/map` (the embedded map in an iframe from a signed link) and `/malls/{mall}/map/qr/{slug}` (the address printed in QR codes). Manual checks: [guide 42](../../../guide/TestByYourself/42_map_graph_checkpoints_and_routes.md) and [guide 43](../../../guide/TestByYourself/43_mall_map_embed_and_docs.md).

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 03: indoor maps navigation](../../references/functional/DOC/modules/03_indoor_maps_navigation/GUIDE.md), including its completion checks.
- [Catalogue section 10: mall owner administration](../../references/functional/DOC/modules/10_mall_owner_administration/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 04-maps-navigation: [overview](../../references/erd/DOC/05-modules/04-maps-navigation/README.md), [business rules](../../references/erd/DOC/05-modules/04-maps-navigation/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/04-maps-navigation/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/04-maps-navigation/ERD.md).

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: MallShopDirectory, ParkingFacilities, MallOwnerAdmin, ChatbotRag. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-07 (target devices decided by D-019; QR start points and routes built by D-021; the alignment accuracy still has to be measured). The ERD domain 04 tables listed below are owned by the map service, not this module (D-021). [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.
