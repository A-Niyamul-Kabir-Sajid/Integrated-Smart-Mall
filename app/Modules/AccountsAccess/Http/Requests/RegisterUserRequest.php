<?php

namespace App\Modules\AccountsAccess\Http\Requests;

use App\Core\Services\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise contact details first so uniqueness is checked on the stored form.
     */
    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone'));

        $this->merge([
            'display_name' => trim((string) $this->input('display_name')),
            'email' => blank($this->input('email')) ? null : Str::lower(trim((string) $this->input('email'))),
            'phone' => $phone === '' ? null : (app(PhoneNumberNormalizer::class)->normalize($phone) ?? $phone),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email:rfc', 'max:254', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'required_without:email', 'string', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => __('Enter an email address or a mobile number.'),
            'phone.required_without' => __('Enter an email address or a mobile number.'),
            'phone.regex' => __('Enter a valid mobile number, for example 01712345678.'),
            'email.unique' => __('An account with this email already exists.'),
            'phone.unique' => __('An account with this mobile number already exists.'),
        ];
    }
}
