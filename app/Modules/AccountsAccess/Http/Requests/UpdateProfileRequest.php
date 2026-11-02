<?php

namespace App\Modules\AccountsAccess\Http\Requests;

use App\Core\Services\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edits the signed-in user's own profile; there is no user id in the request,
 * so one account can never edit another.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

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
        $userId = $this->user()->getKey();

        return [
            'display_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($userId, 'user_id')],
            'phone' => ['nullable', 'required_without:email', 'string', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'phone')->ignore($userId, 'user_id')],
            'current_password' => ['nullable', 'string', 'current_password'],
        ];
    }

    /**
     * Changing a sign-in contact requires the current password.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $contactChanged = $this->input('email') !== $user->email || $this->input('phone') !== $user->phone;

                if ($contactChanged && blank($this->input('current_password'))) {
                    $validator->errors()->add('current_password', __('Enter your current password to change your email or mobile number.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => __('Keep an email address or a mobile number on your account.'),
            'phone.required_without' => __('Keep an email address or a mobile number on your account.'),
            'phone.regex' => __('Enter a valid mobile number, for example 01712345678.'),
            'email.unique' => __('Another account already uses this email.'),
            'phone.unique' => __('Another account already uses this mobile number.'),
            'current_password.current_password' => __('The current password is incorrect.'),
        ];
    }
}
