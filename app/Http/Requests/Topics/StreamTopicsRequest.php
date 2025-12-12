<?php

namespace App\Http\Requests\Topics;

use Illuminate\Foundation\Http\FormRequest;

class StreamTopicsRequest extends FormRequest
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
            'regenerate' => 'nullable|in:true,false,1,0',
            'geographic_focus' => 'nullable|in:nigeria_west_africa,balanced,global',
        ];
    }
}
