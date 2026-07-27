<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceWindowRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:255'],
            'monitors' => ['array'],
            'monitors.*' => ['integer', 'exists:monitors,id'],
            'groups' => ['array'],
            'groups.*' => ['integer', 'exists:monitor_groups,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // A window with no monitors and no groups covers nothing, so it is invalid.
        $validator->after(function (Validator $validator) {
            if (empty($this->input('monitors')) && empty($this->input('groups'))) {
                $validator->errors()->add('monitors', 'Select at least one monitor or group for this window.');
            }
        });
    }
}
