<?php

namespace App\Modules\MapsNavigation\Contracts;

/**
 * Access to the separate map service (map-service/, decisions D-020 and
 * D-021): published maps, their map package, shop signs and advertisements,
 * QR checkpoints, routes and links to the embedded map.
 * Floor files and images come back as temporary signed URLs that a browser
 * can load directly; the API key never leaves the server.
 */
interface MapServiceGateway
{
    /**
     * Published maps: id, name, floor_codes, stats, annotations_count, ...
     *
     * @return list<array<string, mixed>>
     *
     * @throws MapServiceUnavailable
     */
    public function maps(): array;

    /**
     * The full map package of one published map, with file_urls.
     *
     * @return array<string, mixed>
     *
     * @throws MapServiceUnavailable
     */
    public function package(string $mapId): array;

    /**
     * Signs and advertisements of one published map, optionally filtered.
     *
     * @param  array{floor?: string, unit_code?: string, kind?: string}  $filters
     * @return list<array<string, mixed>>
     *
     * @throws MapServiceUnavailable
     */
    public function annotations(string $mapId, array $filters = []): array;

    /**
     * The mall's published map: id, name, floor_codes, stats and places
     * (floors, units, facilities, facility_types, checkpoints).
     *
     * @return array<string, mixed>
     *
     * @throws MapServiceUnavailable reason no_map when the mall has none
     */
    public function mall(string $mallSlug): array;

    /**
     * Where a scanned QR code puts the visitor: checkpoint_code, label,
     * mall_ref, floor_code, node, qr and start (a place for routes).
     *
     * @return array<string, mixed>
     *
     * @throws MapServiceUnavailable reason unknown, retired, not_on_map or no_map
     */
    public function checkpoint(string $slug): array;

    /**
     * A route on the mall's published map. Places are written kind:value
     * (checkpoint:, unit:, facility:, facility-type:, node:).
     *
     * @param  list<string>  $to  destinations, visited in order
     * @param  'time'|'distance'  $cost
     * @return array<string, mixed>
     *
     * @throws MapServiceUnavailable reason unknown_place or unreachable
     */
    public function route(string $mallSlug, string $from, array $to, bool $stepFree = false, string $cost = 'time'): array;

    /**
     * A short-lived signed link to the embedded map, for an iframe.
     *
     * @param  array{from?: string|null, to?: list<string>, step_free?: bool, cost?: string, lang?: string}  $options
     * @return array{url: string, expires_at: string, map_id: string, mall_ref: string|null}
     *
     * @throws MapServiceUnavailable reason no_map, unknown_place or no_origins
     */
    public function embedLink(string $mallSlug, array $options = []): array;
}
