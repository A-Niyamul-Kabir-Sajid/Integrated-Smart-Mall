<?php

namespace App\Modules\AccountsAccess\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\SharedPlatform\Services\AuditLogger;

/**
 * Updates the signed-in user's own profile. A changed email or phone loses its
 * verification; the audit records which fields changed, never their values.
 */
class ProfileService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{display_name: string, email: string|null, phone: string|null}  $data
     */
    public function update(User $user, array $data): User
    {
        $user->fill([
            'display_name' => $data['display_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($user->isDirty('phone')) {
            $user->phone_verified_at = null;
        }

        $changed = array_values(array_intersect(['display_name', 'email', 'phone'], array_keys($user->getDirty())));

        if ($changed === []) {
            return $user;
        }

        $user->save();

        $this->audit->record('account.profile_updated', $user, ['fields' => $changed]);

        return $user;
    }
}
