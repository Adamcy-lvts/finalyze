<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDefenseQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chapter_number' => 'nullable|integer|min:1|max:20',
            'count' => 'nullable|integer|min:1|max:10',
        ];
    }
}
