<?php

namespace App\Modules\ShopOwnerOperations\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\ShopMembership;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Services\MembershipService;
use App\Modules\MallShopDirectory\Enums\ShopStatus;
use App\Modules\MallShopDirectory\Models\Shop;
use App\Modules\SharedPlatform\Enums\Ability;
use App\Modules\SharedPlatform\Services\AuditLogger;
use App\Modules\ShopOwnerOperations\Enums\InvitationStatus;
use App\Modules\ShopOwnerOperations\Enums\ShopFunction;
use App\Modules\ShopOwnerOperations\Models\ShopStaffFunction;
use App\Modules\ShopOwnerOperations\Models\ShopStaffInvitation;
use App\Modules\ShopOwnerOperations\Notifications\StaffInvitationNotice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Staff access of one shop (Jira ISM-101): an owner invites people by email, the
 * invited person accepts with the secret link, and the owner can change a member's
 * work function or remove them. Invitation secrets are 40 random characters, valid
 * for 7 days, stored only as a SHA-256 hash and shown to the owner once.
 */
class StaffService
{
    public const MAX_STAFF = 25;

    public const MAX_PENDING_INVITATIONS = 20;

    public const INVITATION_DAYS = 7;

    public function __construct(
        private MembershipService $memberships,
        private AuditLogger $audit,
    ) {}

    /**
     * Invites an email address to work at the shop. The raw secret is returned once so the
     * owner can share the link when mail is not delivered.
     *
     * @return array{invitation: ShopStaffInvitation, secret: string}
     */
    public function invite(User $owner, Shop $shop, string $email, ShopFunction $function): array
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);
        $this->ensureShopOpenForStaff($shop);

        $email = Str::lower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254) {
            throw new BusinessRuleViolation('staff.invalid_email', __('Enter a valid email address.'));
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && $existing->hasShopRole($shop->getKey(), RoleCode::ShopOwner, RoleCode::ShopStaff)) {
            throw new BusinessRuleViolation('staff.already_member', __('This person already works at the shop.'));
        }

        if ($this->pendingInvitations($shop)->where('email', $email)->isNotEmpty()) {
            throw new BusinessRuleViolation('staff.already_invited', __('This email address already has a pending invitation.'));
        }

        if ($this->pendingInvitations($shop)->count() >= self::MAX_PENDING_INVITATIONS) {
            throw new BusinessRuleViolation('staff.too_many_invitations', __('The shop has too many pending invitations. Revoke some first.'));
        }

        $this->ensureRoomForStaff($shop);

        $secret = Str::random(40);

        $invitation = ShopStaffInvitation::query()->create([
            'shop_id' => $shop->getKey(),
            'email' => $email,
            'staff_function' => $function,
            'secret_hash' => hash('sha256', $secret),
            'invited_by_user_id' => $owner->getKey(),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(self::INVITATION_DAYS),
        ]);

        $this->audit->record('staff.invited', $invitation, ['email' => $email, 'function' => $function->value], $shop->mall_id, $shop->getKey());

        Notification::route('mail', $email)->notify(new StaffInvitationNotice($shop->name, route('staff-invitations.show', $secret), $function->label()));

        return ['invitation' => $invitation, 'secret' => $secret];
    }

    /**
     * The invitation behind a raw secret, whatever its state; null for unknown secrets.
     */
    public function findBySecret(string $secret): ?ShopStaffInvitation
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $secret) !== 1) {
            return null;
        }

        return ShopStaffInvitation::query()->with('shop')->where('secret_hash', hash('sha256', $secret))->first();
    }

    /**
     * Accepts an invitation with the account whose email the invitation was sent to.
     * Unknown, used, revoked, expired and foreign invitations all fail the same way.
     */
    public function accept(User $user, string $secret): ShopStaffInvitation
    {
        $found = $this->findBySecret($secret);

        $fail = fn (): BusinessRuleViolation => new BusinessRuleViolation('staff.invitation_unavailable', __('This invitation cannot be used. Ask the shop owner for a new one.'));

        if ($found === null) {
            throw $fail();
        }

        return DB::transaction(function () use ($user, $found, $fail): ShopStaffInvitation {
            $invitation = ShopStaffInvitation::query()->whereKey($found->getKey())->lockForUpdate()->firstOrFail();
            $shop = $invitation->shop;

            if (! $invitation->isUsable() || $user->email === null || $user->email !== $invitation->email || $shop->status === ShopStatus::Archived) {
                throw $fail();
            }

            if ($user->hasShopRole($shop->getKey(), RoleCode::ShopOwner, RoleCode::ShopStaff)) {
                throw new BusinessRuleViolation('staff.already_member', __('You already work at this shop.'));
            }

            $this->ensureRoomForStaff($shop);

            $membership = $this->memberships->grantShopRole($user, $shop, RoleCode::ShopStaff);
            ShopStaffFunction::query()->updateOrCreate(['shop_membership_id' => $membership->getKey()], ['staff_function' => $invitation->staff_function]);

            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'accepted_by_user_id' => $user->getKey(),
                'accepted_at' => now(),
            ]);

            $this->audit->record('staff.invitation_accepted', $invitation, ['function' => $invitation->staff_function->value], $shop->mall_id, $shop->getKey());

            return $invitation;
        });
    }

    public function revokeInvitation(User $owner, Shop $shop, ShopStaffInvitation $invitation): void
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);
        abort_unless($invitation->shop_id === $shop->getKey(), 404);

        if ($invitation->status !== InvitationStatus::Pending) {
            throw new BusinessRuleViolation('staff.invitation_closed', __('Only a pending invitation can be revoked.'));
        }

        $invitation->update(['status' => InvitationStatus::Revoked]);
        $this->audit->record('staff.invitation_revoked', $invitation, ['email' => $invitation->email], $shop->mall_id, $shop->getKey());
    }

    public function changeFunction(User $owner, Shop $shop, ShopMembership $membership, ShopFunction $function): void
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);
        $this->ensureStaffMembershipOf($shop, $membership);

        ShopStaffFunction::query()->updateOrCreate(['shop_membership_id' => $membership->getKey()], ['staff_function' => $function]);

        $this->audit->record('staff.function_changed', $membership->user, ['function' => $function->value], $shop->mall_id, $shop->getKey());
    }

    public function remove(User $owner, Shop $shop, ShopMembership $membership): void
    {
        Gate::forUser($owner)->authorize(Ability::ManageShop->value, $shop);
        $this->ensureStaffMembershipOf($shop, $membership);

        DB::transaction(function () use ($shop, $membership): void {
            $this->memberships->revokeShopRole($membership->user, $shop, RoleCode::ShopStaff);
            ShopStaffFunction::query()->whereKey($membership->getKey())->delete();
        });
    }

    /**
     * Active staff (not owners) with their functions, by name.
     *
     * @return Collection<int, ShopMembership>
     */
    public function staffOf(Shop $shop): Collection
    {
        $members = ShopMembership::query()->active()
            ->where('shop_id', $shop->getKey())
            ->whereHas('role', fn ($role) => $role->where('code', RoleCode::ShopStaff->value))
            ->with('user:user_id,display_name,email')
            ->get();

        $functions = ShopStaffFunction::query()->whereIn('shop_membership_id', $members->modelKeys())->get()->keyBy('shop_membership_id');

        return $members
            ->each(fn (ShopMembership $membership) => $membership->setRelation('staffFunction', $functions->get($membership->getKey())))
            ->sortBy('user.display_name')
            ->values();
    }

    /**
     * @return Collection<int, ShopStaffInvitation>
     */
    public function pendingInvitations(Shop $shop): Collection
    {
        return ShopStaffInvitation::query()->where('shop_id', $shop->getKey())
            ->where('status', InvitationStatus::Pending)
            ->orderBy('created_at')
            ->get();
    }

    private function ensureStaffMembershipOf(Shop $shop, ShopMembership $membership): void
    {
        abort_unless($membership->shop_id === $shop->getKey() && $membership->status === 'active', 404);

        if ($membership->role->code !== RoleCode::ShopStaff) {
            throw new BusinessRuleViolation('staff.not_staff', __('Only staff members can be changed here.'));
        }
    }

    private function ensureShopOpenForStaff(Shop $shop): void
    {
        if ($shop->status === ShopStatus::Archived) {
            throw new BusinessRuleViolation('staff.shop_archived', __('An archived shop cannot take on staff.'));
        }
    }

    private function ensureRoomForStaff(Shop $shop): void
    {
        if (count($this->staffOf($shop)) >= self::MAX_STAFF) {
            throw new BusinessRuleViolation('staff.too_many', __('A shop can have at most :max staff members.', ['max' => self::MAX_STAFF]));
        }
    }
}
