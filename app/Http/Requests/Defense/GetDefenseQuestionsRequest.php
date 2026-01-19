<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class GetDefenseQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chapter_number' => 'nullable|integer|min:1|max:20',
            'limit' => 'nullable|integer|min:1|max:20',
            'force_refresh' => 'nullable|boolean',
            'difficulty' => 'nullable|string',
            'skip_generation' => 'nullable|boolean',
        ];
    }
}
