<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'project_category_id' => 'required|exists:project_categories,id',
            'type' => 'required|in:undergraduate,postgraduate',
            'university_id' => 'required|exists:universities,id',
            'faculty_id' => 'required|exists:faculties,id',
            'department_id' => 'required|exists:departments,id',
            'course' => 'required|string',
            'field_of_study' => 'nullable|string',
            'supervisor_name' => 'nullable|string',
            'matric_number' => 'nullable|string',
            'academic_session' => 'required|string',
            'degree' => 'required|string',
            'degree_abbreviation' => 'required|string',
            'mode' => 'required|in:auto,manual',
            'ai_assistance_level' => 'nullable|in:minimal,moderate,maximum',
        ];
    }
}
