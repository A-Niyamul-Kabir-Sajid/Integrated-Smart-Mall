<?php

namespace App\Modules\AccountsAccess\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Enums\RoleScope;
use App\Modules\AccountsAccess\Models\Role;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\SharedPlatform\Services\AuditLogger;

/**
 * Grants and removes platform-scoped roles (USER_ROLE). Mall and shop roles
 * are only ever granted through memberships.
 */
class RoleAssignmentService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Idempotent: assigning a role the account already holds changes nothing.
     */
    public function assignPlatformRole(User $user, RoleCode $code): void
    {
        $this->ensurePlatformScope($code);

        if ($user->hasPlatformRole($code)) {
            return;
        }

        $user->roles()->attach(Role::forCode($code), ['assigned_at' => now()]);

        $this->audit->record('access.role_assigned', $user, ['role' => $code->value]);
    }

    public function revokePlatformRole(User $user, RoleCode $code): void
    {
        $this->ensurePlatformScope($code);

        if ($user->roles()->detach(Role::forCode($code)) === 0) {
            return;
        }

        $this->audit->record('access.role_revoked', $user, ['role' => $code->value]);
    }

    private function ensurePlatformScope(RoleCode $code): void
    {
        if ($code->scope() !== RoleScope::Platform) {
            throw new BusinessRuleViolation(
                'user_role.not_platform_scope',
                __('The :role role applies to one mall or shop and is granted through a membership.', ['role' => $code->value]),
            );
        }
    }
}
