<?php

namespace App\Modules\ShopOwnerOperations\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validation shared by every form that sets a listing's own fields.
 */
final class ListingRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'seller_sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'price_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'availability_status' => ['required', Rule::in(['available', 'unavailable', 'unknown'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'seller_sku.regex' => __('Use letters, digits, dots, dashes or underscores in the SKU.'),
            'price_amount.decimal' => __('Enter the price with at most two decimals.'),
        ];
    }
}
