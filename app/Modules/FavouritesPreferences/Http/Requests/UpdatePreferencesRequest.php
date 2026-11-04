<?php

namespace App\Modules\FavouritesPreferences\Http\Requests;

use App\Modules\FavouritesPreferences\Enums\RouteMode;
use App\Modules\SharedPlatform\Services\LocaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Unchecked checkboxes are absent from the request; treat them as off.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn (string $field): bool => $this->boolean($field),
            array_combine($fields = ['ar_preferred', 'personalization_opt_in', 'notify_by_email', 'notify_by_sms'], $fields),
        ));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::in(LocaleService::SUPPORTED)],
            'route_mode' => ['required', Rule::enum(RouteMode::class)],
            'default_mall_id' => ['nullable', 'uuid', Rule::exists('malls', 'mall_id')->where('status', 'active')],
            'ar_preferred' => ['boolean'],
            'personalization_opt_in' => ['boolean'],
            'notify_by_email' => ['boolean'],
            'notify_by_sms' => ['boolean'],
        ];
    }
}
