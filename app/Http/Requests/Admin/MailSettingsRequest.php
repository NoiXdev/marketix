<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access enforced by EnsureSuperAdmin.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'default_mailer' => ['required', Rule::in(['postal', 'smtp', 'log'])],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'postal_url' => ['nullable', 'string', 'max:255'],
            'postal_key' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_scheme' => ['nullable', 'string', 'max:50'],
        ];
    }
}
