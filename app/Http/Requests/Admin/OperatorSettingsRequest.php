<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OperatorSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access enforced by the super_admin route middleware.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'agency_name' => ['nullable', 'string', 'max:255'],
            'agency_address' => ['nullable', 'string', 'max:500'],
            'hosting_provider' => ['nullable', 'string', 'max:255'],
            'hosting_location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
