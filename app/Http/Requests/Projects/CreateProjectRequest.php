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
            'project_id' => 'nullable|integer|exists:projects,id', // Optional: for resuming existing project
            'project_category_id' => 'required|exists:project_categories,id',
            'type' => 'required|in:undergraduate,postgraduate',
            'university_id' => 'required|exists:universities,id',

            // Faculty: either select from database OR enter custom
            'faculty_id' => 'nullable|exists:faculties,id|required_without:custom_faculty',
            'custom_faculty' => 'nullable|string|max:255|required_without:faculty_id',

            // Department: either select from database OR enter custom
            'department_id' => 'nullable|exists:departments,id|required_without:custom_department',
            'custom_department' => 'nullable|string|max:255|required_without:department_id',

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

    public function messages(): array
    {
        return [
            'faculty_id.required_without' => 'Please select a faculty or enter a custom faculty name.',
            'custom_faculty.required_without' => 'Please select a faculty or enter a custom faculty name.',
            'department_id.required_without' => 'Please select a department or enter a custom department name.',
            'custom_department.required_without' => 'Please select a department or enter a custom department name.',
        ];
    }
}
