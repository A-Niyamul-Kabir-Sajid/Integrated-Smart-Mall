<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Services\ApiKeyIssuer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('api-key:create {name : Who uses it, e.g. "Smart Mall app"} {--ability=* : maps:read (default) and/or annotations:write} {--days= : Expire after this many days}')]
#[Description('Create an API key for another app; the key is printed once')]
class CreateApiKey extends Command
{
    public function handle(ApiKeyIssuer $issuer): int
    {
        $abilities = $this->option('ability') ?: ['maps:read'];
        $days = $this->option('days');

        try {
            [$key, $plain] = $issuer->issue((string) $this->argument('name'), $abilities, $days ? now()->addDays((int) $days) : null);
        } catch (InvalidArgumentException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info("Key “{$key->name}” with ".implode(', ', $key->abilities).($key->expires_at ? ', expires '.$key->expires_at->toDateString() : '').'.');
        $this->line('Copy it now, it is not shown again:');
        $this->line($plain);
        $this->line('Abilities available: '.implode(', ', array_keys(ApiKey::ABILITIES)));

        return self::SUCCESS;
    }
}
