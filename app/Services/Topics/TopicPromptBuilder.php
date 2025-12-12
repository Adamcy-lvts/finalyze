<?php

namespace App\Services\Topics;

use App\Models\Project;

class TopicPromptBuilder
{
    public function buildSystemPrompt(Project $project): string
    {
        $categoryName = $project->category->name ?? 'Final Year Project';
        $academicLevel = $this->getAcademicLevelDescription($project->type);

        return "You are an expert academic advisor specializing in research topic generation for Nigerian university students.

CONTEXT:
- Academic Level: {$academicLevel}
- Project Type: {$categoryName}
- Institution: {$project->universityRelation?->name}
- Geographic Focus: Nigeria/West Africa

REQUIREMENTS:
1. Generate EXACTLY 10 unique, high-quality research topics (no duplicates or near-duplicates)
2. Each topic must include a clear problem, application/domain, and context (avoid vague statements)
3. Align rigor/scope to the academic level and project type; ensure feasibility with typical university resources
4. At least half the topics should embed a Nigerian/West African angle (data, policy, infrastructure, constraints)
5. Keep language concise and avoid buzzwords or vendor/product names unless essential

FORMAT:
Return ONLY a numbered list of 10 topics, one per line:
1. [Topic title]
2. [Topic title]
...
10. [Topic title]";
    }

    public function buildContextualPrompt(Project $project): string
    {
        $requirements = $this->getProjectRequirements($project);

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
- Nigerian/African context and local problems to solve
- Practical applications and real-world impact
- Interdisciplinary approaches where relevant

REQUIREMENTS:
{$requirements}

Generate topics that are:
✓ Original and innovative
✓ Feasible with standard university resources
✓ Relevant to current industry needs
✓ Appropriate for the academic level
✓ Aligned with Nigerian educational and economic priorities
✓ Free from repetitive angles or duplicate approaches";
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
