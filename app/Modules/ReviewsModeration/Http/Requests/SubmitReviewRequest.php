<?php

namespace App\Modules\ReviewsModeration\Http\Requests;

use App\Modules\ReviewsModeration\Services\ReviewPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A review form: rating, optional title, text and, for product reviews, an optional purchase
 * code. There is deliberately no field for a verified flag; the server decides that.
 */
class SubmitReviewRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:'.ReviewPolicy::TITLE_MAX],
            'body' => ['required', 'string', 'min:'.ReviewPolicy::BODY_MIN, 'max:'.ReviewPolicy::BODY_MAX],
            'purchase_code' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{rating: int, title: string|null, body: string}
     */
    public function reviewData(): array
    {
        return $this->safe()->only(['rating', 'title', 'body']);
    }
}
