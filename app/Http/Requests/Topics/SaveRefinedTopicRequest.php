<?php

namespace App\Http\Requests\Topics;

use Illuminate\Foundation\Http\FormRequest;

class SaveRefinedTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Project|null $project */
        $project = $this->route('project');

        return $project && $project->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:5000',
            'session_id' => 'nullable|uuid',
        ];
    }
}
