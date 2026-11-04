<?php

namespace App\Modules\AccountsAccess\Http\Requests;

use App\Core\Services\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Guard credentials: the login is an email when it contains "@", otherwise a phone number.
     *
     * @return array{email?: string, phone?: string, password: string}
     */
    public function credentials(): array
    {
        $login = trim($this->string('login')->toString());

        $identifier = str_contains($login, '@')
            ? ['email' => Str::lower($login)]
            : ['phone' => app(PhoneNumberNormalizer::class)->normalize($login) ?? $login];

        return [...$identifier, 'password' => $this->string('password')->toString()];
    }
}
