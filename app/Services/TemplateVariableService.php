<?php

namespace App\Services;

use App\Models\Project;

class TemplateVariableService
{
    /**
     * Get all available template variables with descriptions.
     *
     * @return array<string, string>
     */
    public function getAvailableVariables(): array
    {
        return [
            'student_name' => 'Student\'s full name',
            'student_id' => 'Student ID number',
            'student_id_inline' => 'Student ID prefixed for sentences',
            'matric_number' => 'Matriculation number',
            'department' => 'Department name',
            'academic_session' => 'Academic session (e.g., 2023/2024)',
            'project_title' => 'Project title',
            'project_topic' => 'Project topic',
            'project_type' => 'Project type (e.g., Thesis, Dissertation)',
            'field_of_study' => 'Field of study',
            'degree' => 'Degree being pursued',
            'degree_abbreviation' => 'Degree abbreviation (e.g., B.Sc., M.Sc.)',
            'supervisor_name' => 'Supervisor\'s name',
            'university' => 'University short name',
            'full_university_name' => 'University full name',
            'faculty' => 'Faculty name',
            'course' => 'Course of study',
            'current_year' => 'Current year',
            'submission_date' => 'Project submission date',
        ];
    }

    /**
     * Substitute template variables with actual project data.
     */
    public function substituteVariables(string $template, Project $project): string
    {
        $variables = $this->buildVariableMap($project);

        // Replace all {{variable_name}} patterns
        $result = preg_replace_callback(
            '/\{\{(\w+)(?::or:\[([^\]]*)\])?\}\}/',
            function ($matches) use ($variables) {
                $variableName = $matches[1];
                $fallback = $matches[2] ?? '';

                return $variables[$variableName] ?? $fallback;
            },
            $template
        );

        return $result ?? $template;
    }

    /**
     * Build a map of variable names to their actual values.
     *
     * @return array<string, string>
     */
    protected function buildVariableMap(Project $project): array
    {
        $user = $project->user;
        $settings = $project->settings ?? [];

        $studentId = (string) ($project->student_id ?? '');
        if (trim($studentId) === '' || trim($studentId) === '0') {
            $studentId = (string) ($settings['matric_number'] ?? '');
        }
        $studentId = trim($studentId) === '0' ? '' : trim($studentId);
        $studentIdInline = $studentId !== '' ? ', Student ID: '.$studentId : '';

        $department = (string) ($settings['department'] ?? '');
        if (trim($department) === '') {
            $department = (string) ($project->departmentRelation->name ?? ($project->department_name ?? ''));
        }

        $faculty = (string) ($project->faculty ?? '');
        if (trim($faculty) === '') {
            $faculty = (string) ($project->facultyRelation->name ?? ($project->faculty_name ?? ''));
        }

        $universityShort = (string) ($project->university ?? '');
        $universityFull = (string) ($project->full_university_name ?? '');
        if (trim($universityFull) === '') {
            $universityFull = (string) ($project->universityRelation->name ?? '');
        }
        if (trim($universityShort) === '') {
            $universityShort = (string) ($project->universityRelation->short_name ?? $universityFull);
        }

        $documentType = (string) ($project->document_type ?? $project->type ?? '');
        $documentType = trim($documentType) !== '' ? strtolower($documentType) : '';

        return [
            'student_name' => $user->name ?? '',
            'student_id' => $studentId,
            'student_id_inline' => $studentIdInline,
            'matric_number' => $settings['matric_number'] ?? '',
            'department' => $department,
            'academic_session' => $settings['academic_session'] ?? '',
            'project_title' => $project->title ?? '',
            'project_topic' => $project->topic ?? '',
            'project_type' => $documentType,
            'field_of_study' => $project->field_of_study ?? '',
            'degree' => $project->degree ?? '',
            'degree_abbreviation' => $project->degree_abbreviation ?? '',
            'supervisor_name' => $project->supervisor_name ?? '',
            'university' => $universityShort,
            'full_university_name' => $universityFull,
            'faculty' => $faculty,
            'course' => $project->course ?? '',
            'current_year' => (string) now()->year,
            'submission_date' => $project->submission_date?->format('F j, Y') ?? now()->format('F j, Y'),
        ];
    }

    /**
     * Get variable names only (for UI display).
     *
     * @return array<int, string>
     */
    public function getVariableNames(): array
    {
        return array_keys($this->getAvailableVariables());
    }

    /**
     * Format a variable for insertion (wrapped in {{ }}).
     */
    public function formatVariable(string $variableName): string
    {
        return '{{'.$variableName.'}}';
    }
}
