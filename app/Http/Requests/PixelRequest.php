<?php

namespace App\Http\Requests;

use App\Enums\PixelProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PixelRequest extends FormRequest
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
            'provider' => ['required', 'string', Rule::in(array_column(PixelProvider::cases(), 'value'))],
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['required', 'string', 'max:500'],
        ];
    }
}
