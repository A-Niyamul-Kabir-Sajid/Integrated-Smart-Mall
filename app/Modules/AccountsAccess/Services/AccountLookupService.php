<?php

namespace App\Modules\AccountsAccess\Services;

use App\Core\Services\PhoneNumberNormalizer;
use App\Modules\AccountsAccess\Models\User;
use Illuminate\Support\Str;

/**
 * Finds an active account by the email address or mobile number a person gives at a
 * counter. Callers get the account or null; they learn nothing else about it.
 */
class AccountLookupService
{
    public function __construct(private PhoneNumberNormalizer $phones) {}

    public function activeAccountByContact(string $contact): ?User
    {
        $contact = trim($contact);

        if ($contact === '') {
            return null;
        }

        $user = str_contains($contact, '@')
            ? User::query()->where('email', Str::lower($contact))->first()
            : (($phone = $this->phones->normalize($contact)) === null ? null : User::query()->where('phone', $phone)->first());

        return $user?->isActive() ? $user : null;
    }
}
