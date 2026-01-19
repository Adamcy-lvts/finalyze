<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class StartDefenseSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_panelists' => 'required|array|min:1',
            'selected_panelists.*' => 'string',
            'difficulty_level' => 'nullable|in:undergraduate,masters,doctoral',
            'time_limit_minutes' => 'nullable|integer|min:5|max:120',
            'question_limit' => 'nullable|integer|min:1|max:50',
        ];
    }
}
