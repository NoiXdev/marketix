<?php

namespace App\Http\Requests;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UrlRequest extends FormRequest
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
        // input() (not get()) so the merged Project resolves on both
        // form-encoded and JSON requests.
        $project = $this->input('project');
        $ignoreId = $this->route('url'); // null on store, the URL id on update

        return [
            'domain_id' => ['required', 'ulid', Rule::exists('domains', 'id')->where('project_id', $project->id)],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('urls', 'slug')
                    ->where('domain_id', $this->input('domain_id'))
                    ->ignore($ignoreId),
            ],
            'url' => ['required', 'url', 'max:2048'],
            'type' => ['required', 'integer', Rule::in(array_column(RedirectType::cases(), 'value'))],
            'status' => ['required', 'integer', Rule::in(array_column(UrlStatus::cases(), 'value'))],
            'password' => ['nullable', 'string', 'max:255'],
            'expired_at' => ['nullable', 'date'],

            // Targeting
            'targeting_geo' => ['nullable', 'array'],
            'targeting_geo.*.country' => ['required_with:targeting_geo', 'string', 'size:2'],
            'targeting_geo.*.state' => ['nullable', 'string', 'max:10'],
            'targeting_geo.*.url' => ['required_with:targeting_geo', 'url', 'max:2048'],

            'targeting_device' => ['nullable', 'array'],
            'targeting_device.*.device' => ['required_with:targeting_device', 'string', 'max:50'],
            'targeting_device.*.url' => ['required_with:targeting_device', 'url', 'max:2048'],

            'targeting_language' => ['nullable', 'array'],
            'targeting_language.*.language' => ['required_with:targeting_language', 'string', 'max:10'],
            'targeting_language.*.url' => ['required_with:targeting_language', 'url', 'max:2048'],

            'targeting_ab' => ['nullable', 'array'],
            'targeting_ab.*.url' => ['required_with:targeting_ab', 'url', 'max:2048'],
            'targeting_ab.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
