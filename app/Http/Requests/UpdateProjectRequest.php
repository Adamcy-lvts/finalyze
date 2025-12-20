<?php

namespace App\Http\Requests;

use App\Services\HtmlSanitizerService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && $project->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Basic Information
            // Note: title and topic are readonly and not included
            'description' => 'nullable|string|max:1000',
            'field_of_study' => 'nullable|string|max:255',
            'mode' => 'required|in:auto,manual',

            // Academic Details - Institutional Fields
            'university' => 'required|string|max:255',
            'faculty' => 'required|string|max:255',
            'course' => 'required|string|max:255',
            'supervisor_name' => 'nullable|string|max:255',
            'student_name' => 'nullable|string|max:255',

            // Academic Details - Student Information (stored in settings)
            'settings' => 'nullable|array',
            'settings.department' => 'nullable|string|max:255',
            'settings.matric_number' => 'nullable|string|max:50',
            'settings.academic_session' => 'nullable|string|max:50',
            'settings.ai_assistance_level' => 'nullable|string|in:minimal,moderate,extensive',

            // Preliminary Pages (HTML content - increased max length)
            'dedication' => 'nullable|string|max:20000',
            'acknowledgements' => 'nullable|string|max:30000',
            'abstract' => 'nullable|string|max:20000',
            'declaration' => 'nullable|string|max:20000',
            'certification' => 'nullable|string|max:20000',

            // Preliminary Pages - Complex Fields
            'certification_signatories' => 'nullable|array',
            'certification_signatories.*.name' => 'required|string|max:255',
            'certification_signatories.*.title' => 'required|string|max:255',

            'tables' => 'nullable|array',
            'tables.*.title' => 'nullable|string|max:255',
            'tables.*.description' => 'nullable|string|max:500',

            'abbreviations' => 'nullable|array',
            'abbreviations.*.abbreviation' => 'required|string|max:50',
            'abbreviations.*.full_form' => 'required|string|max:255',
        ];
    }

    /**
     * Get the validated data from the request and sanitize HTML fields.
     *
     * @param  array|int|string|null  $key
     * @param  mixed  $default
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        // If a specific key is requested, return that value
        if ($key !== null) {
            return $validated;
        }

        // Sanitize HTML fields
        $sanitizer = app(HtmlSanitizerService::class);

        if (isset($validated['dedication'])) {
            $validated['dedication'] = $sanitizer->sanitize($validated['dedication']);
        }

        if (isset($validated['acknowledgements'])) {
            $validated['acknowledgements'] = $sanitizer->sanitize($validated['acknowledgements']);
        }

        if (isset($validated['abstract'])) {
            $validated['abstract'] = $sanitizer->sanitize($validated['abstract']);
        }

        if (isset($validated['declaration'])) {
            $validated['declaration'] = $sanitizer->sanitize($validated['declaration']);
        }

        if (isset($validated['certification'])) {
            $validated['certification'] = $sanitizer->sanitize($validated['certification']);
        }

        return $validated;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'mode.required' => 'Please select a working mode.',
            'mode.in' => 'Working mode must be either auto or manual.',
            'university.required' => 'University is required.',
            'faculty.required' => 'Faculty is required.',
            'course.required' => 'Course is required.',
            'certification_signatories.*.name.required' => 'Each signatory must have a name.',
            'certification_signatories.*.title.required' => 'Each signatory must have a title.',
            'abbreviations.*.abbreviation.required' => 'Each abbreviation entry must have an abbreviation.',
            'abbreviations.*.full_form.required' => 'Each abbreviation must have a full form.',
        ];
    }
}
