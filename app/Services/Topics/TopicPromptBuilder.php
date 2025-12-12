<?php

namespace App\Services\Topics;

use App\Models\Project;

class TopicPromptBuilder
{
    public function buildSystemPrompt(Project $project, ?string $geographicFocus = null): string
    {
        $categoryName = $project->category->name ?? 'Final Year Project';
        $academicLevel = $this->getAcademicLevelDescription($project->type);
        $geographicFocus = $geographicFocus ?: 'balanced';

        [$audience, $geoLine, $geoRequirement] = $this->getGeographicFocusPromptParts($geographicFocus);

        return "You are an expert academic advisor specializing in research topic generation for {$audience}.

CONTEXT:
- Academic Level: {$academicLevel}
- Project Type: {$categoryName}
- Institution: {$project->universityRelation?->name}
{$geoLine}

REQUIREMENTS:
1. Generate EXACTLY 10 unique, high-quality research topics (no duplicates or near-duplicates)
2. Each topic must include a clear problem, application/domain, and context (avoid vague statements)
3. Align rigor/scope to the academic level and project type; ensure feasibility with typical university resources
4. {$geoRequirement}
5. Keep language concise and avoid buzzwords or vendor/product names unless essential

FORMAT:
Return ONLY a numbered list of 10 topics, one per line:
1. [Topic title]
2. [Topic title]
...
10. [Topic title]";
    }

    public function buildContextualPrompt(Project $project, ?string $geographicFocus = null): string
    {
        $requirements = $this->getProjectRequirements($project);
        $geographicFocus = $geographicFocus ?: 'balanced';

        [$focusAreasLine, $focusReminder] = $this->getGeographicFocusContextParts($geographicFocus);

        $categoryName = $project->category->name ?? 'Final Year Project';
        $department = $project->departmentRelation?->name
            ?? $project->settings['department']
            ?? ($project->facultyRelation?->name ?? $project->faculty ?? 'Department');
        $university = $project->universityRelation?->name ?? $project->university;
        $academicLevel = $this->getAcademicLevelDescription($project->type);
        $fieldOfStudy = $project->field_of_study ?: 'Field of study not specified';
        $course = $project->course ?: 'Course not specified';

        return "Generate research topics for:

FIELD OF STUDY: {$fieldOfStudy}
COURSE: {$course}
DEPARTMENT: {$department}
UNIVERSITY: {$university}
ACADEMIC LEVEL: {$academicLevel}
PROJECT TYPE: {$categoryName}

FOCUS AREAS:
- Current industry trends and challenges in {$fieldOfStudy}
- Emerging technologies applicable to {$fieldOfStudy}
- {$focusAreasLine}
- Practical applications and real-world impact
- Interdisciplinary approaches where relevant

REQUIREMENTS:
{$requirements}

Generate topics that are:
✓ Original and innovative
✓ Feasible with standard university resources
✓ Relevant to current industry needs
✓ Appropriate for the academic level
✓ {$focusReminder}
✓ Free from repetitive angles or duplicate approaches";
    }

    private function getGeographicFocusPromptParts(string $geographicFocus): array
    {
        return match ($geographicFocus) {
            'nigeria_west_africa' => [
                'Nigerian/West African university students',
                '- Geographic Focus: Nigeria/West Africa',
                'Ensure most topics (at least 8/10) embed a Nigerian/West African angle (data, policy, infrastructure, constraints).',
            ],
            'global' => [
                'university students',
                '- Geographic Focus: Global (do not force a Nigeria-specific angle)',
                'Ensure topics are globally relevant; do not force a Nigerian/West African angle unless it naturally fits the field.',
            ],
            default => [
                'university students (with awareness of Nigerian/West African contexts)',
                '- Geographic Focus: Balanced (Nigeria/West Africa + Global)',
                'Ensure at least half the topics (5/10) embed a Nigerian/West African angle (data, policy, infrastructure, constraints), and the rest are globally relevant.',
            ],
        };
    }

    private function getGeographicFocusContextParts(string $geographicFocus): array
    {
        return match ($geographicFocus) {
            'nigeria_west_africa' => [
                'Nigerian/West African context and local problems to solve',
                'Aligned with Nigerian educational and economic priorities',
            ],
            'global' => [
                'Global context, widely applicable datasets, and international best practices',
                'Aligned with global research standards and industry needs',
            ],
            default => [
                'A balanced mix of Nigerian/West African context and globally relevant problems',
                'Aligned with Nigerian priorities where relevant, without sacrificing global relevance',
            ],
        };
    }

    private function getProjectRequirements(Project $project): string
    {
        $categoryName = $project->category->name ?? '';

        if (str_contains(strtolower($categoryName), 'thesis') || str_contains(strtolower($categoryName), 'dissertation')) {
            return '- Comprehensive literature review required
- Original research contribution expected  
- Statistical analysis and data collection needed
- Duration: 12-18 months
- Significant theoretical and practical contribution';
        }

        return '- Practical implementation component preferred
- Literature review and analysis required
- Prototype/system development expected
- Duration: 6-12 months  
- Clear problem-solution approach';
    }

    public function getAcademicLevelDescription(string $type): string
    {
        return match (strtolower($type)) {
            'undergraduate', 'bachelor' => 'Undergraduate/Bachelor\'s degree level',
            'masters', 'msc', 'ma' => 'Master\'s degree level',
            'phd', 'doctorate' => 'Doctoral/PhD level',
            default => 'Final year project level'
        };
    }
}
