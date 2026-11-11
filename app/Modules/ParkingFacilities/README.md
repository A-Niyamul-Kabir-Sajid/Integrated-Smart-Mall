# ParkingFacilities

Saved parking, facility finder and visitor-support flows built on directory facilities and navigation.

- Namespace: `App\Modules\ParkingFacilities`
- Jira: EPIC-09 Parking & Facilities
- Requirements, business rules and ERD: [DOCS/modules/ParkingFacilities/README.md](../../../DOCS/modules/ParkingFacilities/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented (EPIC-09) except what needs MapsNavigation (EPIC-05): map markers, facility map anchors and routed return paths. Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Owns

| Table | Source | Written by |
|---|---|---|
| `parking_zones` | D-013 (not in the ERD) | `ParkingZoneService` |
| `parking_locations` | D-013 (not in the ERD), key (user_id, mall_id) | `ParkingService` |

Facilities themselves (`facilities`, ERD FACILITY) belong to MallShopDirectory and are written only through its `FacilityService`; this module provides their screens.

## Entry points

| Route | Who | Does |
|---|---|---|
| `GET /malls/{publicMall}/facilities` | anyone, `throttle:search` | facility finder (type, floor, step-free, open only), simulated occupancy, the visitor's own spot and directions back (`?from=<floor code>`) |
| `POST/DELETE /malls/{publicMall}/parking` | signed in, `throttle:parking` | save, replace or clear one's own spot |
| `/admin/malls/{mall}/facilities[...]` | `can:administer-mall` | list, add and edit facilities; lists parking zones |
| `/admin/malls/{mall}/parking-zones[...]` | `can:administer-mall` | add and edit parking zones |

## Services

- `ParkingZoneService`: zones on active floors of the mall, codes unique per mall (upper-case), audited.
- `ParkingService`: one spot per account and mall (upsert), only active zones of that mall; `directionsBack(mall, location, fromFloor)` gives floor-by-floor steps naming the open lift on the starting floor.
- `SimulatedOccupancyFeed`: **simulated** free spaces from capacity and the hour of day (C-09). Never stored; every screen labels it "Simulated data, not live".

## Waits for MapsNavigation (EPIC-05)

Facility map anchors (ERD FACILITY_ANCHOR), markers on the map and a routed path back to the car through the common routing service. Until then the finder groups facilities by floor with location notes, and directions are floor by floor.
