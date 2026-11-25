<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A key another app uses to call /api/v1. Only the SHA-256 hash of the key is
 * stored; the prefix is kept so people can tell keys apart.
 *
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $key_hash
 * @property list<string> $abilities
 * @property list<string>|null $allowed_origins web origins that may frame the embedded map
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 */
class ApiKey extends Model
{
    public const ABILITIES = [
        'maps:read' => 'Read published maps, their package and their signs and advertisements',
        'annotations:write' => 'Create, change and delete signs and advertisements',
        'graph:write' => 'Close and reopen walkways of published maps',
    ];

    protected $fillable = ['name', 'prefix', 'key_hash', 'abilities', 'allowed_origins', 'last_used_at', 'expires_at', 'revoked_at'];

    protected $hidden = ['key_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'allowed_origins' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashOf(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return list<string>
     */
    public function origins(): array
    {
        return array_values($this->allowed_origins ?? []);
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }
}
