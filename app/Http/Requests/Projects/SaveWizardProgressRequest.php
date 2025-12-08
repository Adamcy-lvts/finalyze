<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class SaveWizardProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projectId = $this->input('project_id');

        if (! $projectId) {
            return $this->user() !== null;
        }

        return Project::query()
            ->where('id', $projectId)
            ->where('user_id', $this->user()?->id)
            ->where('status', 'setup')
            ->exists();
    }

    public function rules(): array
    {
        return [
            'project_id' => 'nullable|exists:projects,id',
            'step' => 'required|integer|min:1|max:3',
            'data' => 'required|array',
        ];
    }
}
