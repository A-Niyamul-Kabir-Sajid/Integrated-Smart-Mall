<?php

namespace App\Modules\AccountsAccess\Services;

use App\Modules\AccountsAccess\Enums\RoleCode;
use App\Modules\AccountsAccess\Models\User;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * Creates customer accounts. Input is validated by RegisterUserRequest; the
 * service owns the workflow: account, customer role and audit entry together.
 */
class RegistrationService
{
    public function __construct(
        private RoleAssignmentService $roles,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{display_name: string, email?: string|null, phone?: string|null, password: string}  $data
     */
    public function registerCustomer(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'display_name' => $data['display_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password_hash' => $data['password'],
            ]);

            $this->roles->assignPlatformRole($user, RoleCode::Customer);

            $this->audit->record('account.registered', $user, [
                'channel' => $user->email !== null ? 'email' : 'phone',
            ]);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
