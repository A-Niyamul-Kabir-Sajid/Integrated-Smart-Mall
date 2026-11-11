<?php

namespace App\Modules\FavouritesPreferences\Http\Requests;

use App\Modules\FavouritesPreferences\Services\ShoppingListService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new list line: either a product (from a product page) or free text.
 */
class StoreListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'uuid', Rule::exists('products', 'product_id')->where('status', 'active')],
            'label' => ['required_without:product_id', 'nullable', 'string', 'max:160'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.ShoppingListService::MAX_QUANTITY],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required_without' => __('Type what you want to buy.'),
            'product_id.exists' => __('That product is no longer available.'),
        ];
    }
}
