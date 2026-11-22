# MapsNavigation

## Responsibility

Versioned maps, checkpoints, graph routing and AR coordination. Source folder: [app/Modules/MapsNavigation](../../../app/Modules/MapsNavigation/).

Status: no application code yet. The offline map preparation pipeline exists (ISM-57/ISM-58, decision D-018): [tools/blender/README.md](../../../tools/blender/README.md) turns iPhone LiDAR scans into a map package ([format and sample](../../../tools/blender/samples/pilot_map_package.json)) whose fields follow the ERD below. How to scan: [tools/blender/SCANNING.md](../../../tools/blender/SCANNING.md). Importing that package (migrations, models, import command) is ISM-59.

Map service (D-019): the separate app [map-service/](../../../map-service/README.md) holds imported maps and the shop signs and advertisements placed on them in 3D, and serves them through `/api/v1` with API keys. This module reads it through `Contracts/MapServiceGateway` (adapter `Adapters/HttpMapServiceGateway`, configuration `services.map_service`, check command `php artisan maps:service-check`).

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

Relevant [open questions](../../CONFLICTS.md): C-07 (target devices decided by D-018; the alignment accuracy still has to be measured). [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.
