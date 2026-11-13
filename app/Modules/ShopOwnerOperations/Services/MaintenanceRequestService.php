<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceCategory;
use App\Modules\ShopOwnerOperations\Enums\MaintenancePriority;
use App\Modules\ShopOwnerOperations\Enums\MaintenanceStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopArea;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequest;
use App\Modules\ShopOwnerOperations\Models\MaintenanceRequestEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Maintenance requests from shops to mall management (Jira ISM-107/ISM-109, decision D-014).
 *
 * Managers and owners of an active shop raise a request; the mall's administrators
 * acknowledge it, start work and mark it fixed; the shop confirms (closes) or reopens it.
 * The mall-side screens belong to EPIC-11 (ISM-117) and call updateStatus() and listForMall().
 */
class MaintenanceRequestService
{
    public const MAX_OPEN_PER_SHOP = 20;

    public function __construct(
        private MembershipService $memberships,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{title: string, description: string, category: string, priority: string}  $data
     */
    public function create(User $staff, Shop $shop, array $data): MaintenanceRequest
    {
        Gate::forUser($staff)->authorize(ShopArea::Maintenance->ability(), $shop);

        $category = MaintenanceCategory::tryFrom((string) ($data['category'] ?? ''))
            ?? throw new BusinessRuleViolation('maintenance.invalid_category', __('Choose a category.'));
        $priority = MaintenancePriority::tryFrom((string) ($data['priority'] ?? ''))
            ?? throw new BusinessRuleViolation('maintenance.invalid_priority', __('Choose a priority.'));

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            throw new BusinessRuleViolation('maintenance.invalid_title', __('Give the request a title of 3 to 150 characters.'));
        }

        if (mb_strlen($description) < 5 || mb_strlen($description) > 2000) {
            throw new BusinessRuleViolation('maintenance.invalid_description', __('Describe the problem in 5 to 2000 characters.'));
        }

        $open = MaintenanceRequest::query()->where('shop_id', $shop->getKey())->whereIn('status', array_map(fn (MaintenanceStatus $status): string => $status->value, array_filter(MaintenanceStatus::cases(), fn (MaintenanceStatus $status): bool => $status->isOpen())))->count();

        if ($open >= self::MAX_OPEN_PER_SHOP) {
            throw new BusinessRuleViolation('maintenance.too_many_open', __('Your shop already has :max open maintenance requests.', ['max' => self::MAX_OPEN_PER_SHOP]));
        }

        $request = DB::transaction(function () use ($staff, $shop, $category, $priority, $title, $description): MaintenanceRequest {
            $request = $this->createRequest([
                'shop_id' => $shop->getKey(),
                'mall_id' => $shop->mall_id,
                'requested_by_user_id' => $staff->getKey(),
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'priority' => $priority,
                'status' => MaintenanceStatus::Open,
            ]);

            $this->recordEvent($request, null, MaintenanceStatus::Open, $staff, null);
            $this->audit->record('maintenance.requested', $request, ['number' => $request->request_number, 'priority' => $priority->value], $shop->mall_id, $shop->getKey());

            return $request;
        });

        foreach ($this->memberships->mallAdministrators($shop->loadMissing('mall')->mall) as $administrator) {
            $administrator->notify(new SystemNotice(__('Maintenance request from :shop', ['shop' => $shop->name]), $title, null));
        }

        return $request;
    }

    /**
     * The shop withdraws a request the mall has not started on.
     */
    public function cancel(User $staff, MaintenanceRequest $request): MaintenanceRequest
    {
        $this->authorizeShop($staff, $request);

        return $this->move($request, MaintenanceStatus::Cancelled, $staff, null);
    }

    /**
     * The shop confirms the problem is fixed, which closes the request.
     */
    public function confirm(User $staff, MaintenanceRequest $request): MaintenanceRequest
    {
        $this->authorizeShop($staff, $request);

        return $this->move($request, MaintenanceStatus::Closed, $staff, null, ['closed_at' => now()]);
    }

    /**
     * The shop says it is not fixed after all; the request goes back to being worked on.
     */
    public function reopen(User $staff, MaintenanceRequest $request, string $note): MaintenanceRequest
    {
        $this->authorizeShop($staff, $request);

        $note = trim($note);

        if (mb_strlen($note) < 3 || mb_strlen($note) > 500) {
            throw new BusinessRuleViolation('maintenance.note_required', __('Say what is still wrong, in 3 to 500 characters.'));
        }

        return $this->move($request, MaintenanceStatus::InProgress, $staff, $note);
    }

    /**
     * The mall's administrators move a request to acknowledged, in progress or resolved.
     * Marking it resolved needs a note of what was done.
     */
    public function updateStatus(User $administrator, MaintenanceRequest $request, MaintenanceStatus $to, ?string $note = null): MaintenanceRequest
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $request->loadMissing('mall')->mall);

        if (! in_array($to, [MaintenanceStatus::Acknowledged, MaintenanceStatus::InProgress, MaintenanceStatus::Resolved], true)) {
            throw new BusinessRuleViolation('maintenance.mall_cannot_set', __('The mall can acknowledge, start or resolve a request.'));
        }

        $note = $note === null ? null : trim($note);
        $extra = [];

        if ($to === MaintenanceStatus::Resolved) {
            if ($note === null || mb_strlen($note) < 3 || mb_strlen($note) > 500) {
                throw new BusinessRuleViolation('maintenance.note_required', __('Say what was done, in 3 to 500 characters.'));
            }

            $extra['resolution_note'] = $note;
        }

        $this->move($request, $to, $administrator, $note, $extra);

        foreach ($this->memberships->shopOwners($request->loadMissing('shop')->shop) as $owner) {
            $owner->notify(new SystemNotice(__('Maintenance update'), __(':number is now :status.', ['number' => $request->request_number, 'status' => $to->label()]), route('workspace.maintenance.show', [$request->shop_id, $request])));
        }

        return $request;
    }

    /**
     * @return LengthAwarePaginator<int, MaintenanceRequest>
     */
    public function listForShop(User $staff, Shop $shop, ?MaintenanceStatus $status = null, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($staff)->authorize(ShopArea::Maintenance->ability(), $shop);

        return MaintenanceRequest::query()->where('shop_id', $shop->getKey())
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Requests of every shop in the mall, urgent first, for its administrators.
     *
     * @return LengthAwarePaginator<int, MaintenanceRequest>
     */
    public function listForMall(User $administrator, Mall $mall, ?MaintenanceStatus $status = null, int $perPage = 20): LengthAwarePaginator
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);

        return MaintenanceRequest::query()->where('mall_id', $mall->getKey())
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->with('shop:shop_id,name')
            ->orderByRaw("case priority when 'urgent' then 0 when 'normal' then 1 else 2 end")
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRequest(array $attributes): MaintenanceRequest
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return MaintenanceRequest::query()->create([...$attributes, 'request_number' => 'MNT-'.Str::upper(Str::random(8))]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not allocate a unique request number.');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function move(MaintenanceRequest $request, MaintenanceStatus $to, ?User $actor, ?string $note, array $extra = []): MaintenanceRequest
    {
        return DB::transaction(function () use ($request, $to, $actor, $note, $extra): MaintenanceRequest {
            $locked = MaintenanceRequest::query()->with('shop')->lockForUpdate()->findOrFail($request->getKey());
            $from = $locked->status;

            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new BusinessRuleViolation('maintenance.invalid_transition', __('A :from request cannot become :to.', ['from' => $from->value, 'to' => $to->value]));
            }

            $locked->update([...$extra, 'status' => $to]);
            $this->recordEvent($locked, $from, $to, $actor, $note);
            $this->audit->record('maintenance.'.$to->value, $locked, array_filter(['number' => $locked->request_number, 'note' => $note]), $locked->mall_id, $locked->shop_id);

            $request->setRawAttributes($locked->getAttributes(), true);

            return $request;
        });
    }

    private function recordEvent(MaintenanceRequest $request, ?MaintenanceStatus $from, MaintenanceStatus $to, ?User $actor, ?string $note): void
    {
        MaintenanceRequestEvent::query()->create([
            'maintenance_request_id' => $request->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->getKey(),
            'note' => $note === null ? null : mb_substr($note, 0, 500),
            'created_at' => now(),
        ]);
    }

    private function authorizeShop(User $staff, MaintenanceRequest $request): void
    {
        Gate::forUser($staff)->authorize(ShopArea::Maintenance->ability(), $request->loadMissing('shop')->shop);
    }
}
