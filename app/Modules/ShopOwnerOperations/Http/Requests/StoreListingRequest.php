<?php

namespace App\Modules\ShopOwnerOperations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lists an existing shared variant (mode=existing) or creates a new product (mode=new).
 * Access is checked by the route's work-in-shop ability.
 */
class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['existing', 'new'])],
            'variant_id' => ['exclude_unless:mode,existing', 'required', 'uuid', Rule::exists('product_variants', 'variant_id')],
            'product_category_id' => ['exclude_unless:mode,new', 'required', 'uuid', Rule::exists('product_categories', 'product_category_id')],
            'name' => ['exclude_unless:mode,new', 'required', 'string', 'max:160'],
            'brand' => ['exclude_unless:mode,new', 'nullable', 'string', 'max:100'],
            'description' => ['exclude_unless:mode,new', 'nullable', 'string', 'max:2000'],
            'variant_label' => ['exclude_unless:mode,new', 'required', 'string', 'max:120'],
            'variant_code' => ['exclude_unless:mode,new', 'nullable', 'string', 'max:60'],
            ...ListingRules::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ListingRules::messages();
    }
}
