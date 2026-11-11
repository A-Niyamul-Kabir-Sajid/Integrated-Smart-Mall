<?php

namespace App\Modules\FavouritesPreferences\Http\Requests;

use App\Modules\FavouritesPreferences\Services\ShoppingListService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ticks a line off (or back on), or changes its quantity and note. Only the
 * fields present in the request change.
 */
class UpdateListItemRequest extends FormRequest
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
            'is_done' => ['sometimes', 'boolean'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.ShoppingListService::MAX_QUANTITY],
            'note' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
