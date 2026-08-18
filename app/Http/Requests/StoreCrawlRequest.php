<?php

namespace App\Http\Requests;

use App\Enums\CrawlMode;
use App\Rules\DemoCrawlQuota;
use App\Rules\SafeCrawlUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrawlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already behind auth + ProjectBindingMiddleware
    }

    /**
     * Clamp rather than reject: a demo visitor should not have to guess the
     * page limit, they should just get it.
     */
    protected function prepareForValidation(): void
    {
        if (config('demo.enabled')) {
            $this->merge(['max_pages' => (int) config('demo.crawl.max_pages')]);
        }
    }

    public function rules(): array
    {
        return [
            'start_url' => array_filter([
                'required', 'string', 'max:2048', new SafeCrawlUrl,
                config('demo.enabled') ? new DemoCrawlQuota : null,
            ]),
            'mode' => ['required', Rule::enum(CrawlMode::class)],
            'render_js' => ['boolean'],
            'respect_robots' => ['boolean'],
            'include_subdomains' => ['boolean'],
            'crawl_sitemap' => ['boolean'],
            'capture_screenshots' => ['boolean'],
            'delay_ms' => ['required', 'integer', 'min:0', 'max:10000'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
