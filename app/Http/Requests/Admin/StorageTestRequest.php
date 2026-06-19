<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorageTestRequest extends FormRequest
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
            'driver'            => ['required', Rule::in(['local', 's3'])],
            's3_key'            => ['nullable', 'string', 'max:255'],
            's3_secret'         => ['nullable', 'string', 'max:255'],
            's3_region'         => ['nullable', 'string', 'max:255'],
            's3_bucket'         => ['nullable', 'string', 'max:255'],
            's3_endpoint'       => ['nullable', 'string', 'max:255'],
            's3_use_path_style' => ['boolean'],
        ];
    }
}
