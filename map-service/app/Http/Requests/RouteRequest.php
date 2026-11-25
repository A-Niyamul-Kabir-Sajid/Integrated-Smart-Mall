<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A route question: from one place to one or more places (in order),
 * optionally step-free, by time (default) or distance.
 */
class RouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('to'))) {
            $this->merge(['to' => [$this->input('to')]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'max:200'],
            'to' => ['required', 'array', 'min:1', 'max:10'],
            'to.*' => ['required', 'string', 'max:200'],
            'step_free' => ['nullable', 'boolean'],
            'cost' => ['nullable', 'in:time,distance'],
        ];
    }

    /**
     * @return list<string>
     */
    public function stops(): array
    {
        return array_values(array_map('strval', $this->validated('to')));
    }

    public function stepFree(): bool
    {
        return $this->boolean('step_free');
    }

    /**
     * @return 'time'|'distance'
     */
    public function cost(): string
    {
        return $this->validated('cost') === 'distance' ? 'distance' : 'time';
    }
}
