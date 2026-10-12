<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tenant access is enforced by ProjectBindingMiddleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => strtolower(trim($this->input('name')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('domains', 'name')->ignore($this->route('domain'))->withoutTrashed(),
            ],
            'redirect_root' => ['nullable', 'url', 'max:2048'],
            'redirect_not_found' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('domains.form.name_taken'),
        ];
    }
}
