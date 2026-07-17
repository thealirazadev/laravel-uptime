<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertChannelRequest extends FormRequest
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
        return [
            'type' => ['required', Rule::in(['mail', 'slack', 'webhook'])],
            'name' => ['required', 'string', 'max:255'],
            'to' => ['nullable', 'required_if:type,mail', 'email', 'max:255'],
            'webhook_url' => ['nullable', 'required_if:type,slack', 'url:https', 'max:2048'],
            'url' => ['nullable', 'required_if:type,webhook', 'url:https', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
