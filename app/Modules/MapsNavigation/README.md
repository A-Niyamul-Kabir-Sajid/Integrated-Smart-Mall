# MapsNavigation

Versioned floor maps, QR checkpoints, the navigation graph, routing and AR coordination.

- Namespace: `App\Modules\MapsNavigation`
- Jira: EPIC-05 Maps & Navigation
- Requirements, business rules and ERD: [DOCS/modules/MapsNavigation/README.md](../../../DOCS/modules/MapsNavigation/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

The map, its navigation graph, QR codes, routes, the embedded map and AR directions live in the separate map service (`map-service/`, decisions D-020, D-021 and D-023). This module holds the client (`Contracts/MapServiceGateway`, adapter `Adapters/HttpMapServiceGateway`) and the visitor pages `/malls/{mall}/map` and `/malls/{mall}/map/qr/{slug}`. Their iframe allows `xr-spatial-tracking`, so the embedded map can offer **View in AR** on Android phones. Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).
