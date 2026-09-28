<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QrTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tenant access is enforced by ProjectBindingMiddleware.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Optional on update so a template can be renamed without resending its style.
            'style' => [$this->isMethod('put') ? 'sometimes' : 'required', 'array'],
        ];
    }
}
