<?php

namespace App\Modules\MapsNavigation\Contracts;

/**
 * Read access to the separate map service (map-service/, decision D-020):
 * published maps, their map package and their shop signs and advertisements.
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
}
