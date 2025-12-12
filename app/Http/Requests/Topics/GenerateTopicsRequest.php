<?php

namespace App\Http\Requests\Topics;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTopicsRequest extends FormRequest
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
            'regenerate' => 'sometimes|boolean',
            'geographic_focus' => 'sometimes|in:nigeria_west_africa,balanced,global',
        ];
    }
}
