<?php

namespace App\Http\Requests;

use App\Enums\GoalType;
use App\Models\Funnel;
use App\Support\Analytics\PathMatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $steps = $this->input('steps');
        if (! is_array($steps)) {
            return;
        }

        $this->merge(['steps' => array_values(array_map(function ($step) {
            if (! is_array($step)) {
                return $step;
            }

            $value = is_string($step['value'] ?? null) ? trim($step['value']) : ($step['value'] ?? null);
            if (($step['type'] ?? null) === GoalType::Pageview->value && is_string($value)) {
                $value = PathMatcher::normalize($value);
            }

            $label = is_string($step['label'] ?? null) ? trim($step['label']) : null;

            return ['type' => $step['type'] ?? null, 'value' => $value, 'label' => $label === '' ? null : $label];
        }, $steps))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'steps' => ['required', 'array', 'min:'.Funnel::MIN_STEPS, 'max:'.Funnel::MAX_STEPS],
            'steps.*.type' => ['required', Rule::in(array_column(GoalType::cases(), 'value'))],
            'steps.*.value' => ['required', 'string', 'max:255'],
            'steps.*.label' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'steps.*.value' => __('analytics.funnels.form.step_value'),
            'steps.*.label' => __('analytics.funnels.form.step_label'),
        ];
    }
}
