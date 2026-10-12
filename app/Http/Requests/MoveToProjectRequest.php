<?php

namespace App\Http\Requests;

use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class MoveToProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access to the source project is enforced by ProjectBindingMiddleware
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    $accessible = $value !== $this->input('project')->id
                        && $this->user()->accessibleProjects()->whereKey($value)->exists();

                    if (! $accessible) {
                        $fail(__('move.errors.project'));
                    }
                },
            ],
            'domain_id' => ['nullable', 'string'],
        ];
    }

    public function targetProject(): Project
    {
        return Project::findOrFail($this->validated('project_id'));
    }

    public function targetDomain(Url $url): Domain
    {
        $domain = $this->targetProject()->domains()->find($this->validated('domain_id'));

        if (! $domain) {
            throw ValidationException::withMessages(['domain_id' => __('move.errors.domain')]);
        }

        $taken = Url::withTrashed()
            ->where('domain_id', $domain->id)
            ->where('slug', $url->slug)
            ->whereKeyNot($url->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['domain_id' => __('move.errors.slug_taken', ['slug' => $url->slug, 'domain' => $domain->name])]);
        }

        return $domain;
    }
}
