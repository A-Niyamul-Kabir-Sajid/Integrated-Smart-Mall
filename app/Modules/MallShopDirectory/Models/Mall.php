<?php

namespace App\Modules\MallShopDirectory\Models;

use App\Modules\MallShopDirectory\Enums\MallStatus;
use Database\Factories\MallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * MALL: the boundary for the directory, maps and mall-scoped permissions.
 */
#[Table(name: 'malls', key: 'mall_id')]
#[Fillable(['slug', 'name', 'address', 'city', 'timezone', 'status'])]
#[UseFactory(MallFactory::class)]
class Mall extends Model
{
    /** @use HasFactory<MallFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', MallStatus::Active->value);
    }

    protected function casts(): array
    {
        return [
            'status' => MallStatus::class,
        ];
    }
}
