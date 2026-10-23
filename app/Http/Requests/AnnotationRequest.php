<?php

namespace App\Http\Requests;

use App\Models\Annotation;
use Illuminate\Foundation\Http\FormRequest;

class AnnotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after:1999-12-31', 'before:2101-01-01'],
            'title' => ['required', 'string', 'max:'.Annotation::TITLE_MAX],
            'description' => ['nullable', 'string', 'max:'.Annotation::DESCRIPTION_MAX],
        ];
    }
}
