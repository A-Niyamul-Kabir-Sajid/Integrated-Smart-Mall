<?php

namespace App\Modules\ChatbotRag\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One question to the assistant. Anyone may ask the public assistant; the administrators' route
 * adds its own access check before this request is reached.
 */
class AskAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $this->input('message')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.(int) config('assistant.max_message_length', 500)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => __('Type a question first.'),
            'message.string' => __('Type a question first.'),
            'message.max' => __('Keep the question under :max characters.'),
        ];
    }
}
