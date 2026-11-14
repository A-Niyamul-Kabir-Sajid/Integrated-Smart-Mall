<?php

namespace App\Modules\MallOwnerAdmin\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\AccountLookupService;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Floor;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\MallShopDirectory\Models\ShopCategory;
use App\Modules\MallShopDirectory\Models\ShopUnit;
use App\Modules\MallShopDirectory\Services\ShopService;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Notifications\SystemNotice;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\OrderStatus;
use App\Modules\ShopOwnerOperations\Models\ShopOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The mall administrators' management of tenants (Jira ISM-110/ISM-111, decision D-015): they
 * create a shop for a tenant, correct its profile, category and unit, name its owners and
 * move it between draft, active and archived. Suspension and application approval stay in
 * ShopOwnerOperations (D-014). Every method re-checks administer-mall for the shop's own mall.
 */
class TenantAdministrationService
{
    public const PER_PAGE = 20;

    public const REASON_MIN = 5;

    public function __construct(
        private ShopService $shops,
        private MembershipService $memberships,
        private AccountLookupService $accounts,
        private AuditLogger $audit,
    ) {}

    /**
     * Shops of the mall in every status, newest changes first, for the administration list.
     *
     * @param  array{q?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, Shop>
     */
    public function list(User $administrator, Mall $mall, array $filters = []): LengthAwarePaginator
    {
        $this->authorize($administrator, $mall);

        $status = ShopStatus::tryFrom((string) ($filters['status'] ?? ''));
        $term = trim((string) ($filters['q'] ?? ''));

        return $mall->shops()
            ->with(['category:shop_category_id,name', 'units' => fn ($units) => $units->where('status', '!=', 'archived')->with('floor:floor_id,code,name')])
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($term !== '', fn ($query) => $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower(addcslashes($term, '%_\\')).'%']))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Creates a draft shop for a tenant, optionally placing it in a unit and naming its first owner.
     *
     * @param  array{slug: string, name: string, description?: string|null, contact_phone?: string|null, floor_id?: string|null, unit_code?: string|null, area_sq_m?: float|string|null, owner_contact?: string|null}  $data
     */
    public function create(User $administrator, Mall $mall, ShopCategory $category, array $data): Shop
    {
        $this->authorize($administrator, $mall);

        $floor = filled($data['floor_id'] ?? null) ? $this->floorOf($mall, $data['floor_id']) : null;

        if ($floor !== null && ! filled($data['unit_code'] ?? null)) {
            throw new BusinessRuleViolation('tenant.unit_code_required', __('Enter the unit code on the chosen floor.'));
        }

        $owner = filled($data['owner_contact'] ?? null) ? $this->ownerAccount($data['owner_contact']) : null;

        $shop = DB::transaction(function () use ($mall, $category, $data, $floor, $owner): Shop {
            $shop = $this->shops->create($mall, $category, [
                'slug' => $data['slug'],
                'name' => trim($data['name']),
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'contact_phone' => filled($data['contact_phone'] ?? null) ? $data['contact_phone'] : null,
            ]);

            if ($floor !== null) {
                $this->shops->assignUnit($shop, $floor, $data['unit_code'], filled($data['area_sq_m'] ?? null) ? (float) $data['area_sq_m'] : null);
            }

            if ($owner !== null) {
                $this->memberships->grantShopRole($owner, $shop, RoleCode::ShopOwner);
            }

            return $shop;
        });

        if ($owner !== null) {
            $owner->notify(new SystemNotice(__('You were made owner of :shop', ['shop' => $shop->name]), __('The mall office set up the shop. It becomes active once the office approves it.'), null));
        }

        return $shop;
    }

    /**
     * Corrects the profile and category of a shop that is not archived.
     *
     * @param  array{name: string, description?: string|null, contact_phone?: string|null}  $profile
     */
    public function update(User $administrator, Mall $mall, Shop $shop, ShopCategory $category, array $profile): Shop
    {
        $this->authorizeShop($administrator, $mall, $shop);

        return DB::transaction(function () use ($shop, $category, $profile): Shop {
            $this->shops->updateProfile($shop, $profile);
            $this->shops->changeCategory($shop, $category);

            return $shop;
        });
    }

    public function assignUnit(User $administrator, Mall $mall, Shop $shop, string $floorId, string $unitCode, ?float $areaSqM = null): ShopUnit
    {
        $this->authorizeShop($administrator, $mall, $shop);

        return $this->shops->assignUnit($shop, $this->floorOf($mall, $floorId), $unitCode, $areaSqM);
    }

    public function releaseUnit(User $administrator, Mall $mall, Shop $shop, ShopUnit $unit): ShopUnit
    {
        $this->authorizeShop($administrator, $mall, $shop);

        return $this->shops->releaseUnit($shop, $unit);
    }

    /**
     * A draft shop opens once it has an owner who can run it.
     */
    public function activate(User $administrator, Mall $mall, Shop $shop): Shop
    {
        $this->authorizeShop($administrator, $mall, $shop);

        if ($shop->status !== ShopStatus::Draft) {
            throw new BusinessRuleViolation('tenant.not_draft', __('Only a draft shop can be activated here; a suspended shop is reopened from the shop applications page.'));
        }

        if ($this->memberships->shopOwners($shop)->isEmpty()) {
            throw new BusinessRuleViolation('tenant.owner_required', __('Name an owner for the shop before activating it.'));
        }

        $this->shops->changeStatus($shop, ShopStatus::Active, 'activated by the mall office');
        $this->tellOwners($shop, __(':shop is active', ['shop' => $shop->name]), __('Your shop is now open for business.'));

        return $shop;
    }

    /**
     * Retires a shop for good. Its history stays; open orders must be finished first.
     */
    public function archive(User $administrator, Mall $mall, Shop $shop, string $reason): Shop
    {
        $this->authorizeShop($administrator, $mall, $shop);

        $reason = trim($reason);

        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > 1000) {
            throw new BusinessRuleViolation('tenant.reason_required', __('Write a reason of :min to 1000 characters.', ['min' => self::REASON_MIN]));
        }

        if (ShopOrder::query()->where('shop_id', $shop->getKey())->whereIn('status', array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::openCases()))->exists()) {
            throw new BusinessRuleViolation('tenant.open_orders', __('The shop still has open orders. Finish or cancel them before archiving the shop.'));
        }

        DB::transaction(function () use ($shop, $reason): void {
            $this->shops->changeStatus($shop, ShopStatus::Archived, 'archived: '.$reason);

            foreach ($shop->units()->where('status', '!=', 'archived')->get() as $unit) {
                $this->shops->releaseUnit($shop, $unit);
            }
        });

        $this->tellOwners($shop, __(':shop was archived', ['shop' => $shop->name]), $reason);

        return $shop;
    }

    /**
     * Names an account (by email or mobile number) as an owner of the shop.
     */
    public function addOwner(User $administrator, Mall $mall, Shop $shop, string $contact): User
    {
        $this->authorizeShop($administrator, $mall, $shop);

        if ($shop->status === ShopStatus::Archived) {
            throw new BusinessRuleViolation('tenant.shop_archived', __('An archived shop cannot get a new owner.'));
        }

        $owner = $this->ownerAccount($contact);
        $this->memberships->grantShopRole($owner, $shop, RoleCode::ShopOwner);

        $owner->notify(new SystemNotice(__('You were made owner of :shop', ['shop' => $shop->name]), __('You can now manage the shop from your account page.'), null));

        return $owner;
    }

    /**
     * Removes an owner. An active shop always keeps at least one.
     */
    public function removeOwner(User $administrator, Mall $mall, Shop $shop, User $owner): void
    {
        $this->authorizeShop($administrator, $mall, $shop);

        $owners = $this->memberships->shopOwners($shop);

        if (! $owners->contains(fn (User $candidate): bool => $candidate->is($owner))) {
            throw new BusinessRuleViolation('tenant.not_an_owner', __('This person is not an owner of the shop.'));
        }

        if ($shop->status === ShopStatus::Active && $owners->count() === 1) {
            throw new BusinessRuleViolation('tenant.last_owner', __('An active shop needs at least one owner. Add another owner first.'));
        }

        $this->memberships->revokeShopRole($owner, $shop, RoleCode::ShopOwner);
    }

    /**
     * Active owners of the shop, for the edit screen.
     *
     * @return Collection<int, User>
     */
    public function ownersOf(User $administrator, Mall $mall, Shop $shop): Collection
    {
        $this->authorizeShop($administrator, $mall, $shop);

        return $this->memberships->shopOwners($shop);
    }

    private function authorize(User $administrator, Mall $mall): void
    {
        Gate::forUser($administrator)->authorize(Ability::AdministerMall->value, $mall);
    }

    private function authorizeShop(User $administrator, Mall $mall, Shop $shop): void
    {
        $this->authorize($administrator, $mall);

        if ($shop->mall_id !== $mall->getKey()) {
            throw new BusinessRuleViolation('tenant.shop_in_other_mall', __('This shop belongs to another mall.'));
        }
    }

    private function floorOf(Mall $mall, string $floorId): Floor
    {
        return $mall->floors()->whereKey($floorId)->first()
            ?? throw new BusinessRuleViolation('tenant.floor_not_found', __('Choose a floor of this mall.'));
    }

    private function ownerAccount(string $contact): User
    {
        return $this->accounts->activeAccountByContact($contact)
            ?? throw new BusinessRuleViolation('tenant.owner_not_found', __('No active account has that email address or mobile number. Ask the tenant to register first.'));
    }

    private function tellOwners(Shop $shop, string $title, string $body): void
    {
        foreach ($this->memberships->shopOwners($shop) as $owner) {
            $owner->notify(new SystemNotice($title, $body, null));
        }
    }
}
