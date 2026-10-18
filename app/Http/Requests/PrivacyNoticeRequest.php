<?php

namespace App\Http\Requests;

use App\Enums\ControllerRole;
use App\Enums\LegalBasis;
use App\Support\Locales;
use App\Support\Privacy\NoticeOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrivacyNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tenant access is already enforced by ProjectBindingMiddleware.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(ControllerRole::class)],
            'legal_basis' => ['required', Rule::enum(LegalBasis::class)],
            'locale' => ['required', Rule::in(Locales::codes())],
            'controller_name' => ['nullable', 'string'],
            'controller_address' => ['nullable', 'string'],
            'agency_name' => ['nullable', 'string'],
            'agency_address' => ['nullable', 'string'],
            'hosting_provider' => ['nullable', 'string'],
            'hosting_location' => ['nullable', 'string'],
            'disabled_activities' => ['sometimes', 'array'],
            'disabled_activities.*' => ['string', Rule::in(NoticeOptions::ACTIVITIES)],
        ];
    }
}
