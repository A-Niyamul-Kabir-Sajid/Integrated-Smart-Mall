<?php

namespace App\Http\Requests;

use App\Services\AnnotationStore;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One sign or advertisement in the viewer's JSON shape (API create and update).
 */
class AnnotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return AnnotationStore::rules();
    }
}
