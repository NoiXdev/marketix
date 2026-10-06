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
        // Pageview goals match on a path — ensure a leading slash on any non-empty match_value.
        if ($this->input('type') === GoalType::Pageview->value && is_string($this->input('match_value'))) {
            $mv = trim($this->input('match_value'));
            if ($mv !== '' && ! str_starts_with($mv, '/')) {
                $this->merge(['match_value' => '/'.$mv]);
            }
        }

        // Property conditions only exist for events; untouched blank rows from the form are dropped.
        $conditions = $this->input('type') === GoalType::Event->value && is_array($this->input('conditions'))
            ? array_values(array_filter($this->input('conditions'), fn ($c) => filled($c['property'] ?? null) || filled($c['value'] ?? null)))
            : [];

        $this->merge([
            'conditions' => $conditions === [] ? null : $conditions,
            // A currency only means something next to a value
            'currency' => filled($this->input('value')) && is_string($this->input('currency')) ? strtoupper(trim($this->input('currency'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(GoalType::cases(), 'value'))],
            'match_value' => ['required', 'string', 'max:255'],
            'conditions' => ['nullable', 'array', 'max:5'],
            // Property names end up in a JSON path, so keep them to plain identifiers
            'conditions.*.property' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'conditions.*.value' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['nullable', 'required_with:value', 'string', 'size:3', 'alpha'],
        ];
    }
}
