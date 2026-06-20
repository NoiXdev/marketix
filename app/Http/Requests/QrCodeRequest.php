<?php

namespace App\Http\Requests;

use App\Enums\UrlStatus;
use App\Support\QrTarget;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QrCodeRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:30'],
            'is_dynamic' => ['required', 'boolean'],
            'content' => ['required', 'array'],
            'content.*' => ['nullable'],
            'content.extra' => ['nullable', 'string'],
            'style' => ['required', 'array'],
            'style.foreground' => ['required', 'string'],
            'style.background' => ['required', 'string'],
            'style.dot_style' => ['required', 'string'],
            'style.corner_square_style' => ['required', 'string'],
            'style.corner_dot_style' => ['required', 'string'],
            'style.logo_type' => ['required', 'in:none,predefined,custom'],
            'style.logo_name' => ['nullable', 'string'],
            'style.logo_data' => ['nullable', 'string'],
            'style.logo_size' => ['required', 'integer', 'min:10', 'max:60'],
        ];

        // Dynamic QRs are backed by a real short link.
        if ($this->boolean('is_dynamic')) {
            $project = $this->input('project');

            if ($this->filled('url_id')) {
                // Attach mode: reuse an existing project link instead of
                // creating one. domain_id/slug are display-only and ignored.
                $rules['url_id'] = [
                    'ulid',
                    Rule::exists('urls', 'id')->where('project_id', $project->id),
                ];
            } else {
                $ignoreUrlId = $project->qrCodes()->find($this->route('qrCode'))?->url_id;

                $rules['domain_id'] = ['required', 'ulid', Rule::exists('domains', 'id')->where('project_id', $project->id)];
                $rules['slug'] = [
                    'required', 'string', 'max:255', 'alpha_dash',
                    Rule::unique('urls', 'slug')
                        ->where('domain_id', $this->input('domain_id'))
                        ->ignore($ignoreUrlId),
                ];
            }

            // Link settings apply to the backing Url in both attach and
            // create modes. status is nullable here (defaulted server-side);
            // the standalone Links form requires it.
            $rules['status'] = ['nullable', 'integer', Rule::in(array_column(UrlStatus::cases(), 'value'))];
            $rules['password'] = ['nullable', 'string', 'max:255'];
            $rules['expired_at'] = ['nullable', 'date'];
            $rules = array_merge($rules, \App\Http\Requests\UrlRequest::targetingRules());
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Every dynamic type except vCard must resolve to a non-empty
            // redirect target (vCard is served as a file, not redirected).
            if ($this->boolean('is_dynamic') && $this->input('type') !== 'vcard') {
                $target = QrTarget::redirectTarget($this->input('type'), $this->input('content', []));
                if (trim($target) === '') {
                    $validator->errors()->add('content', 'Please provide the destination for this QR code.');
                }
            }

            // Attach mode: the chosen link must not already carry a QR code.
            if ($this->boolean('is_dynamic') && $this->filled('url_id')) {
                $project = $this->input('project');
                $url = $project->urls()->with('qrCode')->find($this->input('url_id'));
                if ($url && $url->qrCode !== null) {
                    $validator->errors()->add('url_id', 'This link already has a QR code.');
                }
            }
        });
    }
}
