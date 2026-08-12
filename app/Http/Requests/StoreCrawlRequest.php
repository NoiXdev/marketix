<?php

namespace App\Http\Requests;

use App\Enums\CrawlMode;
use App\Rules\SafeCrawlUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrawlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already behind auth + ProjectBindingMiddleware
    }

    public function rules(): array
    {
        return [
            'start_url' => ['required', 'string', 'max:2048', new SafeCrawlUrl],
            'mode' => ['required', Rule::enum(CrawlMode::class)],
            'render_js' => ['boolean'],
            'respect_robots' => ['boolean'],
            'include_subdomains' => ['boolean'],
            'delay_ms' => ['required', 'integer', 'min:0', 'max:10000'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
