<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class StreamGenerateDefenseQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chapter_number' => 'nullable|integer|min:1|max:10',
            'count' => 'nullable|integer|min:1|max:10',
            'focus' => 'nullable|in:methodology,literature,findings,theory,contribution,general',
        ];
    }
}
