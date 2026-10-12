<?php

namespace App\Http\Requests;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tenant access enforced by ProjectBindingMiddleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
            'additional_hostnames' => ['nullable', 'string', 'max:1000', 'regex:/^[a-z0-9.-]+(, [a-z0-9.-]+)*$/'],
            'timezone' => ['sometimes', 'required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'tracking_mode' => ['required', Rule::in(array_column(TrackingMode::cases(), 'value'))],
            'consent_mode' => ['required', Rule::in(ConsentMode::selectableValues())],
            'consent_signal' => ['nullable', 'required_if:consent_mode,'.ConsentMode::ThirdPartySignal->value, 'string', 'max:255'],
            'respect_dnt' => ['boolean'],
            'track_outbound_links' => ['boolean'],
            'track_file_downloads' => ['boolean'],
            'site_search_params' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-\[\]]+(,[A-Za-z0-9_\-\[\]]+)*$/'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('analytics.sites.form.name'),
            'domain' => __('analytics.sites.form.domain'),
            'additional_hostnames' => __('analytics.sites.form.additional_hostnames'),
            'timezone' => __('analytics.sites.form.timezone'),
            'consent_signal' => __('analytics.sites.form.consent_signal'),
            'retention_days' => __('analytics.sites.form.retention_days'),
            'site_search_params' => __('analytics.sites.measurement.search_params'),
        ];
    }

    public function messages(): array
    {
        return [
            'consent_signal.required_if' => __('analytics.sites.form.consent_signal_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('additional_hostnames'))) {
            $hosts = preg_split('/[\s,]+/', strtolower($this->input('additional_hostnames')), -1, PREG_SPLIT_NO_EMPTY);
            $this->merge(['additional_hostnames' => $hosts === [] ? null : implode(', ', array_unique($hosts))]);
        }

        if (! $this->has('site_search_params')) {
            return;
        }

        $params = array_filter(array_map('trim', explode(',', (string) $this->input('site_search_params'))));

        $this->merge(['site_search_params' => $params === [] ? null : implode(',', array_unique($params))]);
    }
}
