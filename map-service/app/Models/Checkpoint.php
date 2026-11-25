<?php

namespace App\Models;

use Database\Factories\CheckpointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A QR checkpoint of one mall (ERD QR_CHECKPOINT). The public slug is what the
 * printed code carries; it never contains coordinates or a map version.
 *
 * @property int $id
 * @property string $mall_ref
 * @property string $checkpoint_code
 * @property string $public_slug
 * @property string|null $label
 * @property string $status active or retired
 * @property Carbon|null $retired_at
 */
class Checkpoint extends Model
{
    /** @use HasFactory<CheckpointFactory> */
    use HasFactory;

    protected $fillable = ['mall_ref', 'checkpoint_code', 'public_slug', 'label', 'status', 'retired_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['retired_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
