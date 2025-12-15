<?php

namespace App\Http\Requests\Topics;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class SelectTopicRequest extends FormRequest
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
            'topic' => 'required|string|max:1500',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::error('❌ TOPIC SELECTION - Validation failed', [
            'user_id' => $this->user()?->id,
            'errors' => $validator->errors()->toArray(),
            'request_data' => $this->all(),
        ]);

        parent::failedValidation($validator);
    }
}
