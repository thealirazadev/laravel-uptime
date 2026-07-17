<?php

namespace App\Http\Requests;

use App\Models\Monitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keyword = $this->input('expected_keyword');

        $this->merge([
            'expected_keyword' => is_string($keyword) && trim($keyword) !== '' ? trim($keyword) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'interval_seconds' => ['required', 'integer', Rule::in(Monitor::INTERVALS)],
            'timeout_seconds' => ['required', 'integer', 'between:1,30'],
            'expected_status' => ['required', 'integer', 'between:100,599'],
            'expected_keyword' => ['nullable', 'string', 'max:255'],
            'confirmation_threshold' => ['required', 'integer', 'between:1,10'],
            'is_active' => ['required', 'boolean'],
            'channels' => ['array'],
            'channels.*' => ['integer', 'exists:alert_channels,id'],
        ];
    }
}
