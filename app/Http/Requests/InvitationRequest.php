<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access enforced by ProjectBindingMiddleware + EnsureProjectAdmin.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Project $project */
            $project = $this->get('project');
            $email = $this->input('email');

            $alreadyMember = $project->users()
                ->where('email', $email)
                ->wherePivot('active', true)
                ->exists();

            if ($alreadyMember) {
                $validator->errors()->add('email', 'This person is already a member of the project.');
            }
        });
    }
}
