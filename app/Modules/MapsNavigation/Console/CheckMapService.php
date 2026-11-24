<?php

namespace App\Modules\MapsNavigation\Console;

use App\Modules\MapsNavigation\Contracts\MapServiceGateway;
use App\Modules\MapsNavigation\Contracts\MapServiceUnavailable;
use Illuminate\Console\Command;

/**
 * Checks the connection to the separate map service and lists what this app can read.
 */
class CheckMapService extends Command
{
    protected $signature = 'maps:service-check';

    protected $description = 'Check the map service connection and API key, and list its published maps';

    public function handle(MapServiceGateway $maps): int
    {
        $this->line('Map service: '.config('services.map_service.url'));

        try {
            $list = $maps->maps();
        } catch (MapServiceUnavailable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        if ($list === []) {
            $this->warn('Connected, but the map service has no published maps yet. Publish one on its Maps page.');

            return self::SUCCESS;
        }

        $this->info('Connected. Published maps:');
        $this->table(
            ['Id', 'Name', 'Floors', 'Nodes', 'QR codes', 'Signs and ads'],
            array_map(fn (array $map): array => [
                $map['id'],
                $map['name'],
                implode(', ', $map['floor_codes'] ?? []),
                $map['stats']['nodes'] ?? 0,
                $map['stats']['checkpoints'] ?? 0,
                $map['annotations_count'] ?? 0,
            ], $list),
        );

        return self::SUCCESS;
    }
}
