<?php

namespace App\Modules\ParkingFacilities\Http\Requests;

use App\Modules\MallShopDirectory\Enums\FacilityStatus;
use App\Modules\MallShopDirectory\Enums\FacilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A facility as a mall administrator enters it. Whether the floor belongs to
 * the mall is a business rule checked by FacilityService.
 */
class FacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_accessible' => $this->boolean('is_accessible')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'floor_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'facility_type' => ['required', Rule::enum(FacilityType::class)],
            'is_accessible' => ['boolean'],
            'status' => [Rule::requiredIf($this->isMethod('PUT')), Rule::enum(FacilityStatus::class)],
            'location_note' => ['nullable', 'string', 'max:160'],
        ];
    }
}
