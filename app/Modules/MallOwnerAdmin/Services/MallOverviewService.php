<?php

namespace App\Modules\MallOwnerAdmin\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\ReviewsModeration\Services\ModerationService;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\ShopOwnerOperations\Enums\ApplicationStatus;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use App\Modules\ShopOwnerOperations\Models\ShopApplication;
use Illuminate\Support\Facades\Gate;

/**
 * The counts on the mall administrators' home page: what is waiting for them, gathered from
 * the modules that own each queue (Jira ISM-110/ISM-118, decision D-015).
 */
class MallOverviewService
{
    public function __construct(private ModerationService $moderation) {}

    /**
     * @return array{shops: array<string, int>, floors: int, waiting: array<string, int>}
     */
    public function summary(User $administrator, Mall $mall): array
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        $shops = $mall->shops()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'shops' => collect(ShopStatus::cases())->mapWithKeys(fn (ShopStatus $status): array => [$status->value => (int) ($shops[$status->value] ?? 0)])->all(),
            'floors' => $mall->floors()->active()->count(),
            'waiting' => [
                'applications' => ShopApplication::query()->where('mall_id', $mall->getKey())->where('status', ApplicationStatus::Pending->value)->count(),
                'maintenance' => MaintenanceRequest::query()->where('mall_id', $mall->getKey())->whereIn('status', [MaintenanceStatus::Open->value, MaintenanceStatus::Acknowledged->value, MaintenanceStatus::InProgress->value])->count(),
                'reported_reviews' => $this->moderation->reportedReviews($administrator, $mall, 1)->total(),
            ],
        ];
    }
}
