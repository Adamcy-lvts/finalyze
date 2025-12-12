<?php

namespace App\Http\Requests\Topics;

use Illuminate\Foundation\Http\FormRequest;

class TopicChatRequest extends FormRequest
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
            'messages' => 'required|array',
            'messages.*.role' => 'required|string|in:user,assistant',
            'messages.*.content' => 'required|string',
            'topic_context' => 'required|array',
            'session_id' => 'required|uuid',
        ];
    }
}
