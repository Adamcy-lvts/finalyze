<?php

namespace App\Http\Requests\Topics;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class ApproveTopicRequest extends FormRequest
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
            'approved' => 'required|boolean',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        /** @var \App\Models\Project|null $project */
        $project = $this->route('project');

        Log::error('❌ TOPIC APPROVAL - Validation failed', [
            'project_id' => $project?->id,
            'validation_errors' => $validator->errors()->toArray(),
            'request_data' => $this->all(),
        ]);

        parent::failedValidation($validator);
    }
}
