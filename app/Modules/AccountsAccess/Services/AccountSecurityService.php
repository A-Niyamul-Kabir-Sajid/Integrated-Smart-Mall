<?php

namespace App\Modules\AccountsAccess\Services;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\AccountsAccess\Notifications\PasswordChanged;
use App\Modules\SharedPlatform\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Password changes and resets. Both rotate the remember-me token and end other
 * sessions, audit the change (never the password) and notify the account holder.
 */
class AccountSecurityService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * A signed-in user changed their password; the current session stays signed in.
     */
    public function changePassword(User $user, string $newPassword, ?string $currentSessionId): void
    {
        $this->replacePassword($user, $newPassword, keepSessionId: $currentSessionId);

        $this->audit->record('account.password_changed', $user, ['channel' => 'account_page']);
        $user->notify(new PasswordChanged('change'));
    }

    /**
     * The password was reset with an emailed link; every session ends.
     */
    public function resetPassword(User $user, string $newPassword): void
    {
        $this->replacePassword($user, $newPassword, keepSessionId: null);

        $this->audit->record('account.password_reset', $user, ['channel' => 'email_link']);
        event(new PasswordReset($user));
        $user->notify(new PasswordChanged('reset'));
    }

    private function replacePassword(User $user, string $newPassword, ?string $keepSessionId): void
    {
        DB::transaction(function () use ($user, $newPassword, $keepSessionId): void {
            $user->forceFill([
                'password_hash' => $newPassword,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
                ->delete();
        });
    }
}
