<?php

namespace App\Http\Requests;

use App\Reports\ReportTypeRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduledReportRequest extends FormRequest
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
        $registry = $this->container->make(ReportTypeRegistry::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_keys($registry->all()))],
            'subject_id' => ['nullable', 'string'],
            'frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'weekday' => ['required_if:frequency,weekly', 'nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['required_if:frequency,monthly', 'nullable', 'integer', 'min:1', 'max:28'],
            'period' => ['required', 'string', Rule::in(['last_7_days', 'last_30_days', 'last_90_days', 'previous_month'])],
            'formats' => ['required', 'array'],
            'formats.csv' => ['required', 'boolean'],
            'formats.pdf' => ['required', 'boolean'],
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['email'],
            'active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');

            if (! is_string($type)) {
                return;
            }

            $registry = $this->container->make(ReportTypeRegistry::class);

            if (! array_key_exists($type, $registry->all())) {
                // Already flagged by the `type` rule above.
                return;
            }

            $project = $this->get('project');
            $subjectId = $this->input('subject_id');

            if (! $registry->for($type)->validateSubject($project, $subjectId)) {
                $validator->errors()->add('subject_id', 'The selected subject is not valid for this report type.');
            }
        });
    }
}
