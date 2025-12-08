<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class TopicSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Project|null $project */
        $project = $this->route('project');

        return $project && $project->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [];
    }
}
