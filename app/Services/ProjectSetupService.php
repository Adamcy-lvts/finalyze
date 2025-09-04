<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use App\Models\ProjectMetadata;
use Illuminate\Support\Str;

class ProjectSetupService
{
    /**
     * Create project with all necessary setup
     */
    public function createProject(array $data, $user): Project
    {
        // Generate unique slug
        $slug = $this->generateUniqueSlug($data['projectName'] ?? $data['fieldOfStudy']);

        // Create main project
        $project = Project::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => $data['projectName'] ?? "New {$data['projectType']} Project",
            'type' => $data['projectType'],
            'project_category_id' => $data['projectCategoryId'],
            'university' => $data['university'],
            'course' => $data['course'],
            'field_of_study' => $data['fieldOfStudy'],
            'supervisor_name' => $data['supervisorName'],
            'mode' => $data['workingMode'],
            'status' => 'topic_selection',
            'settings' => [
                'ai_assistance_level' => $data['aiAssistanceLevel'],
                'auto_save' => $data['autoSave'] ?? true,
                'smart_citations' => $data['smartCitations'] ?? true,
                'plagiarism_check' => $data['plagiarismCheck'] ?? true,
            ],
        ]);

        // Create metadata
        ProjectMetadata::create([
            'project_id' => $project->id,
            'faculty' => $data['faculty'],
            'department' => $data['department'],
            'matriculation_number' => $data['matricNumber'],
            'academic_session' => $data['academicSession'],
        ]);

        // Initialize chapters based on project type
        $this->initializeChapters($project);

        // Track onboarding completion
        $this->trackOnboardingMetrics($project, $user);

        return $project;
    }

    /**
     * Initialize chapter structure based on project type
     */
    private function initializeChapters(Project $project): void
    {
        $chapterTemplates = $this->getChapterTemplates($project->type);

        foreach ($chapterTemplates as $index => $template) {
            Chapter::create([
                'project_id' => $project->id,
                'chapter_number' => $index + 1,
                'title' => $template['title'],
                'description' => $template['description'],
                'target_word_count' => $template['word_count'],
                'status' => 'not_started',
            ]);
        }
    }

    /**
     * Get chapter templates based on project type
     */
    private function getChapterTemplates(string $type): array
    {
        $templates = [
            'undergraduate' => [
                ['title' => 'Introduction', 'description' => 'Background, objectives, and scope', 'word_count' => 2000],
                ['title' => 'Literature Review', 'description' => 'Review of existing research', 'word_count' => 3000],
                ['title' => 'Methodology', 'description' => 'Research methods and approach', 'word_count' => 2500],
                ['title' => 'Implementation/Analysis', 'description' => 'Project implementation or data analysis', 'word_count' => 3500],
                ['title' => 'Results & Discussion', 'description' => 'Findings and their implications', 'word_count' => 2500],
                ['title' => 'Conclusion', 'description' => 'Summary and recommendations', 'word_count' => 1500],
            ],
            'masters' => [
                // Add masters-specific chapters
            ],
            'phd' => [
                // Add PhD-specific chapters
            ],
            'research' => [
                // Add research paper sections
            ],
        ];

        return $templates[$type] ?? $templates['undergraduate'];
    }

    /**
     * Track onboarding metrics for analytics
     */
    private function trackOnboardingMetrics(Project $project, $user): void
    {
        // Log onboarding completion time
        $onboardingTime = now()->diffInSeconds($user->created_at);

        // activity()
        //     ->performedOn($project)
        //     ->causedBy($user)
        //     ->withProperties([
        //         'onboarding_time' => $onboardingTime,
        //         'setup_method' => 'wizard',
        //         'ai_mode' => $project->mode,
        //     ])
        //     ->log('Project created via enhanced wizard');
    }

    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (Project::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
