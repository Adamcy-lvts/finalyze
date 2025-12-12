<?php

namespace App\Services\PromptSystem;

use App\Models\Project;

class ContextMatcher
{
    /**
     * Faculty keyword mappings for detection
     */
    private const FACULTY_KEYWORDS = [
        'engineering' => ['engineering', 'technology', 'technical'],
        'social_science' => ['social science', 'sociology', 'psychology', 'political science', 'economics', 'anthropology'],
        'healthcare' => ['nursing', 'health', 'medicine', 'medical', 'pharmacy', 'public health'],
        'business' => ['business', 'management', 'accounting', 'finance', 'marketing', 'entrepreneurship'],
        'science' => ['science', 'physics', 'chemistry', 'biology', 'biochemistry', 'microbiology'],
        'arts' => ['arts', 'humanities', 'literature', 'philosophy', 'history', 'linguistics'],
        'education' => ['education', 'teaching', 'pedagogy', 'curriculum'],
        'law' => ['law', 'legal', 'jurisprudence'],
        'agriculture' => ['agriculture', 'agronomy', 'agricultural', 'farming', 'crop science'],
    ];

    /**
     * Department keyword mappings for engineering sub-types
     */
    private const ENGINEERING_DEPARTMENTS = [
        'electrical' => ['electrical', 'electronics', 'electronic', 'power', 'control systems'],
        'computer' => ['computer', 'software', 'computing', 'information technology', 'it'],
        'mechanical' => ['mechanical', 'mechatronics', 'automotive'],
        'civil' => ['civil', 'structural', 'construction', 'building'],
        'chemical' => ['chemical', 'petroleum', 'petrochemical'],
    ];

    /**
     * Project type keywords for specialized matching
     */
    private const PROJECT_TYPE_KEYWORDS = [
        'software' => ['software', 'application', 'app', 'system', 'web', 'mobile', 'database', 'api', 'blockchain'],
        'hardware' => ['circuit', 'arduino', 'microcontroller', 'embedded', 'sensor', 'iot', 'pcb', 'electronics'],
        'survey_research' => ['survey', 'questionnaire', 'respondents', 'perception', 'attitude', 'opinion'],
        'experimental' => ['experiment', 'laboratory', 'lab', 'test', 'trial'],
        'case_study' => ['case study', 'case analysis', 'organizational study'],
        'clinical' => ['patient', 'clinical', 'intervention', 'treatment', 'nursing care', 'health outcomes'],
    ];

    /**
     * Match project to academic context hierarchy
     *
     * @return array{faculty: string, department: ?string, course: ?string, field: ?string, project_type: ?string}
     */
    public function match(Project $project): array
    {
        $faculty = $this->detectFaculty($project);
        $department = $this->detectDepartment($project, $faculty);
        $projectType = $this->detectProjectType($project);

        return [
            'faculty' => $faculty,
            'department' => $department,
            'course' => $this->normalizeString($project->course),
            'field' => $this->normalizeString($project->field_of_study),
            'project_type' => $projectType,
            'academic_level' => $this->normalizeString($project->type),
        ];
    }

    /**
     * Detect faculty from project data
     */
    public function detectFaculty(Project $project): string
    {
        // Check explicit faculty field first
        $facultyField = $this->normalizeString($project->faculty ?? '');
        foreach (self::FACULTY_KEYWORDS as $faculty => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($facultyField, $keyword)) {
                    return $faculty;
                }
            }
        }

        // Check field of study
        $fieldOfStudy = $this->normalizeString($project->field_of_study ?? '');
        foreach (self::FACULTY_KEYWORDS as $faculty => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($fieldOfStudy, $keyword)) {
                    return $faculty;
                }
            }
        }

        // Check course name
        $course = $this->normalizeString($project->course ?? '');
        foreach (self::FACULTY_KEYWORDS as $faculty => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($course, $keyword)) {
                    return $faculty;
                }
            }
        }

        // Check topic for keywords
        $topic = $this->normalizeString($project->topic ?? '');
        foreach (self::FACULTY_KEYWORDS as $faculty => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($topic, $keyword)) {
                    return $faculty;
                }
            }
        }

        return 'general';
    }

    /**
     * Detect department (sub-faculty) from project data
     */
    public function detectDepartment(Project $project, string $faculty): ?string
    {
        // Only detect department for engineering (most variation)
        if ($faculty !== 'engineering') {
            return $this->normalizeString($project->department ?? null);
        }

        $searchText = $this->normalizeString(
            ($project->department ?? '').' '.
            ($project->course ?? '').' '.
            ($project->field_of_study ?? '').' '.
            ($project->topic ?? '')
        );

        foreach (self::ENGINEERING_DEPARTMENTS as $department => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($searchText, $keyword)) {
                    return $department;
                }
            }
        }

        return $this->normalizeString($project->department ?? null);
    }

    /**
     * Detect project type from topic and description
     */
    public function detectProjectType(Project $project): ?string
    {
        $searchText = $this->normalizeString(
            ($project->topic ?? '').' '.
            ($project->description ?? '').' '.
            ($project->field_of_study ?? '')
        );

        $scores = [];
        foreach (self::PROJECT_TYPE_KEYWORDS as $type => $keywords) {
            $scores[$type] = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($searchText, $keyword)) {
                    $scores[$type]++;
                }
            }
        }

        // Get highest scoring type
        arsort($scores);
        $topType = array_key_first($scores);

        return $scores[$topType] > 0 ? $topType : null;
    }

    /**
     * Get the best matching template context for a project
     * Returns contexts in order of specificity (most specific first)
     */
    public function getMatchingContexts(Project $project): array
    {
        $context = $this->match($project);
        $contexts = [];

        // Level 5: Topic keywords (most specific)
        if ($context['project_type']) {
            $contexts[] = [
                'type' => 'topic_keyword',
                'value' => $context['project_type'],
                'priority' => 50,
            ];
        }

        // Level 4: Field of study
        if ($context['field']) {
            $contexts[] = [
                'type' => 'field_of_study',
                'value' => $context['field'],
                'priority' => 40,
            ];
        }

        // Level 3: Course
        if ($context['course']) {
            $contexts[] = [
                'type' => 'course',
                'value' => $context['course'],
                'priority' => 30,
            ];
        }

        // Level 2: Department
        if ($context['department']) {
            $contexts[] = [
                'type' => 'department',
                'value' => $context['department'],
                'priority' => 20,
            ];
        }

        // Level 1: Faculty (least specific but always present)
        $contexts[] = [
            'type' => 'faculty',
            'value' => $context['faculty'],
            'priority' => 10,
        ];

        return $contexts;
    }

    /**
     * Check if project matches a specific context
     */
    public function matchesContext(Project $project, string $contextType, string $contextValue): bool
    {
        $context = $this->match($project);
        $normalizedValue = $this->normalizeString($contextValue);

        return match ($contextType) {
            'faculty' => $context['faculty'] === $normalizedValue,
            'department' => $context['department'] === $normalizedValue,
            'course' => str_contains($context['course'] ?? '', $normalizedValue),
            'field_of_study' => str_contains($context['field'] ?? '', $normalizedValue),
            'topic_keyword' => $context['project_type'] === $normalizedValue,
            default => false,
        };
    }

    /**
     * Get all detectable faculties
     */
    public function getAvailableFaculties(): array
    {
        return array_keys(self::FACULTY_KEYWORDS);
    }

    /**
     * Get all detectable project types
     */
    public function getAvailableProjectTypes(): array
    {
        return array_keys(self::PROJECT_TYPE_KEYWORDS);
    }

    /**
     * Normalize string for comparison
     */
    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtolower(trim($value));
    }
}
