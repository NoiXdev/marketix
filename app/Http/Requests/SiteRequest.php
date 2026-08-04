<?php

namespace App\Http\Requests;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tenant access enforced by ProjectBindingMiddleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
            'tracking_mode' => ['required', Rule::in(array_column(TrackingMode::cases(), 'value'))],
            'consent_mode' => ['required', Rule::in(array_column(ConsentMode::cases(), 'value'))],
            'consent_signal' => ['nullable', 'string', 'max:255'],
            'respect_dnt' => ['boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
