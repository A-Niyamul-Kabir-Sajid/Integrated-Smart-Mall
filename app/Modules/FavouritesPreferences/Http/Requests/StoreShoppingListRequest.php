<?php

namespace App\Modules\FavouritesPreferences\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShoppingListRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'mall_id' => [Rule::requiredIf($this->isMethod('POST')), 'uuid', Rule::exists('malls', 'mall_id')->where('status', 'active')],
        ];
    }
}
