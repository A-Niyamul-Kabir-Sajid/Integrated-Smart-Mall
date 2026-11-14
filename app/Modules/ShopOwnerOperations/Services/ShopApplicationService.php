<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Services\ShopService;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\ApplicationStatus;
use App\Modules\ShopOwnerOperations\Models\ShopApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The shop application lifecycle (Jira ISM-101): a signed-in customer applies for a
 * shop in a mall, the mall's administrators approve or reject it, and only an
 * approved (active) shop becomes operational. The shop itself is a draft SHOP row
 * owned by MallShopDirectory and changed only through its ShopService.
 */
class ShopApplicationService
{
    public const MAX_PENDING_PER_APPLICANT = 3;

    public const REASON_MIN = 5;

    public function __construct(
        private ShopService $shops,
        private MembershipService $memberships,
        private AuditLogger $audit,
    ) {}

    /**
     * Creates the draft shop, makes the applicant its owner and files the application.
     *
     * @param  array{name: string, description?: string|null, contact_phone?: string|null, message?: string|null}  $data
     */
    public function apply(User $applicant, Mall $mall, ShopCategory $category, array $data): ShopApplication
    {
        if ($mall->status !== MallStatus::Active) {
            throw new BusinessRuleViolation('shop_application.mall_not_open', __('This mall is not accepting shop applications.'));
        }

        $pending = ShopApplication::query()->where('applicant_user_id', $applicant->getKey())->where('status', ApplicationStatus::Pending)->count();

        if ($pending >= self::MAX_PENDING_PER_APPLICANT) {
            throw new BusinessRuleViolation('shop_application.too_many_pending', __('You already have :max applications waiting for a decision.', ['max' => self::MAX_PENDING_PER_APPLICANT]));
        }

        $name = trim($data['name'] ?? '');

        if ($name === '' || mb_strlen($name) > 150) {
            throw new BusinessRuleViolation('shop_application.invalid_name', __('Enter a shop name of up to 150 characters.'));
        }

        $application = DB::transaction(function () use ($applicant, $mall, $category, $data, $name): ShopApplication {
            $shop = $this->shops->create($mall, $category, [
                'slug' => $this->freeSlug($mall, $name),
                'name' => $name,
                'description' => $data['description'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
            ]);

            $this->memberships->grantShopRole($applicant, $shop, RoleCode::ShopOwner);

            $application = ShopApplication::query()->create([
                'shop_id' => $shop->getKey(),
                'mall_id' => $mall->getKey(),
                'applicant_user_id' => $applicant->getKey(),
                'status' => ApplicationStatus::Pending,
                'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
            ]);

            $this->audit->record('shop_application.submitted', $application, ['shop' => $shop->name], $mall->getKey(), $shop->getKey());

            return $application;
        });

        foreach ($this->memberships->mallAdministrators($mall) as $administrator) {
            $administrator->notify(new SystemNotice(
                __('New shop application'),
                __(':shop applied to open in :mall.', ['shop' => $application->shop->name, 'mall' => $mall->name]),
                route('shop-applications.admin.index', $mall),
            ));
        }

        return $application;
    }

    /**
     * Approves a pending application: the shop becomes active and operational.
     */
    public function approve(User $administrator, ShopApplication $application): ShopApplication
    {
        $this->authorizeDecision($administrator, $application);

        DB::transaction(function () use ($administrator, $application): void {
            $this->shops->changeStatus($application->shop, ShopStatus::Active, 'application approved');
            $this->recordDecision($administrator, $application, ApplicationStatus::Approved, null);
        });

        $this->tell($application, __('Your shop was approved'), __(':shop is now active. You can open your workspace.', ['shop' => $application->shop->name]));

        return $application;
    }

    /**
     * Rejects a pending application with a written reason; the draft shop is archived.
     */
    public function reject(User $administrator, ShopApplication $application, string $reason): ShopApplication
    {
        $this->authorizeDecision($administrator, $application);

        $reason = trim($reason);

        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > 1000) {
            throw new BusinessRuleViolation('shop_application.reason_required', __('Write a reason of :min to 1000 characters.', ['min' => self::REASON_MIN]));
        }

        DB::transaction(function () use ($administrator, $application, $reason): void {
            $this->shops->changeStatus($application->shop, ShopStatus::Archived, $reason);
            $this->recordDecision($administrator, $application, ApplicationStatus::Rejected, $reason);
        });

        $this->tell($application, __('Your shop application was not approved'), $reason);

        return $application;
    }

    /**
     * The mall's applications, pending first, for its administrators.
     *
     * @return LengthAwarePaginator<int, ShopApplication>
     */
    public function listForMall(User $administrator, Mall $mall, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        return ShopApplication::query()->where('mall_id', $mall->getKey())
            ->with(['shop', 'applicant:user_id,display_name,email'])
            ->orderByRaw("case status when 'pending' then 0 else 1 end")
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * The applicant's own applications, newest first.
     *
     * @return Collection<int, ShopApplication>
     */
    public function listForApplicant(User $applicant): Collection
    {
        return ShopApplication::query()->where('applicant_user_id', $applicant->getKey())
            ->with(['shop:shop_id,name,status', 'mall:mall_id,name'])
            ->latest('created_at')
            ->get();
    }

    private function authorizeDecision(User $administrator, ShopApplication $application): void
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $application->mall);

        if ($application->status !== ApplicationStatus::Pending) {
            throw new BusinessRuleViolation('shop_application.already_decided', __('This application was already decided.'));
        }
    }

    private function recordDecision(User $administrator, ShopApplication $application, ApplicationStatus $status, ?string $reason): void
    {
        $application->update([
            'status' => $status,
            'decision_reason' => $reason,
            'decided_by_user_id' => $administrator->getKey(),
            'decided_at' => now(),
        ]);

        $this->audit->record('shop_application.'.$status->value, $application, array_filter(['reason' => $reason]), $application->mall_id, $application->shop_id);
    }

    private function tell(ShopApplication $application, string $title, string $body): void
    {
        $application->applicant->notify(new SystemNotice($title, $body, route('shop-applications.index')));
    }

    private function freeSlug(Mall $mall, string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $base = Str::limit($base, 90, '');
        $slug = $base;

        while ($mall->shops()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
