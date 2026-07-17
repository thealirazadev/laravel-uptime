<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
    }

    /**
     * Type is immutable on edit so the stored config shape never mismatches the
     * type. Secret/URL fields are optional here: a blank field keeps the current
     * (masked) value rather than clearing it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
            'to' => ['nullable', 'email', 'max:255'],
            'webhook_url' => ['nullable', 'url:https', 'max:2048'],
            'url' => ['nullable', 'url:https', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
