<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorageSettingsRequest extends FormRequest
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
            'driver' => ['required', Rule::in(['local', 's3'])],
            's3_key' => ['required_if:driver,s3', 'nullable', 'string', 'max:255'],
            's3_secret' => ['nullable', 'string', 'max:255'],
            's3_region' => ['required_if:driver,s3', 'nullable', 'string', 'max:255'],
            's3_bucket' => ['required_if:driver,s3', 'nullable', 'string', 'max:255'],
            's3_endpoint' => ['nullable', 'url', 'max:255'],
            's3_use_path_style' => ['boolean'],
        ];
    }
}
