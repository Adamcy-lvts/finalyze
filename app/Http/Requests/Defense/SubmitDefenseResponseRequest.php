<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDefenseResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response' => 'required|string|min:3|max:8000',
            'response_time_ms' => 'nullable|integer|min:0|max:600000',
        ];
    }
}
