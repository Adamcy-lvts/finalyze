<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class NextDefenseQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'persona' => 'nullable|string',
            'request_hint' => 'nullable|boolean',
        ];
    }
}
