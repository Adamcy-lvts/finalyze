<?php

namespace App\Http\Requests\Topics;

use Illuminate\Foundation\Http\FormRequest;

class TopicIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
