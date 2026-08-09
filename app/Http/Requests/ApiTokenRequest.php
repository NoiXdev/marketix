<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access enforced by the `auth` middleware
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
