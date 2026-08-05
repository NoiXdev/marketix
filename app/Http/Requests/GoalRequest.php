<?php

namespace App\Http\Requests;

use App\Enums\GoalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tenant access enforced by ProjectBindingMiddleware
    }

    protected function prepareForValidation(): void
    {
        // Pageview goals match on a path — ensure a leading slash (except a '*' prefix wildcard alone).
        if ($this->input('type') === GoalType::Pageview->value && is_string($this->input('match_value'))) {
            $mv = trim($this->input('match_value'));
            if ($mv !== '' && ! str_starts_with($mv, '/')) {
                $this->merge(['match_value' => '/'.$mv]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(GoalType::cases(), 'value'))],
            'match_value' => ['required', 'string', 'max:255'],
        ];
    }
}
