<?php

namespace App\Modules\MallOwnerAdmin\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * Reads the audit trail of one mall for its administrators (Jira ISM-112, decision D-015):
 * who changed what and when. Records are only ever read here; AuditLogger writes them.
 */
class ActivityLogService
{
    public const PER_PAGE = 30;

    /**
     * @param  array{area?: string|null}  $filters  area is the part of the action before the dot, e.g. "shop"
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function forMall(User $administrator, Mall $mall, array $filters = []): LengthAwarePaginator
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $area = trim((string) ($filters['area'] ?? ''));

        $page = AuditLog::query()
            ->where('mall_id', $mall->getKey())
            ->when($area !== '', fn ($query) => $query->where('action', 'like', $area.'.%'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('audit_log_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $actors = User::query()->whereIn('user_id', $page->pluck('actor_id')->filter()->unique())->pluck('display_name', 'user_id');

        $page->getCollection()->each(fn (AuditLog $entry) => $entry->setAttribute('actor_name', $entry->actor_id === null ? null : ($actors[$entry->actor_id] ?? null)));

        return $page;
    }

    /**
     * The areas that have records for this mall, for the filter.
     *
     * @return list<string>
     */
    public function areas(User $administrator, Mall $mall): array
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        return AuditLog::query()->where('mall_id', $mall->getKey())->distinct()->pluck('action')
            ->map(fn (string $action): string => strtok($action, '.'))->unique()->sort()->values()->all();
    }
}
