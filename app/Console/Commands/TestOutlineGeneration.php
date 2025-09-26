<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ProjectOutlineService;
use Illuminate\Console\Command;

class TestOutlineGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:outline-generation {project_id : The ID of the project to generate outline for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test project outline generation for a specific project';

    /**
     * Execute the console command.
     */
    public function handle(ProjectOutlineService $outlineService)
    {
        $projectId = $this->argument('project_id');
        $project = Project::find($projectId);

        if (! $project) {
            $this->error("Project with ID {$projectId} not found");

            return 1;
        }

        $this->info("Testing outline generation for project: {$project->title}");
        $this->info("Topic: {$project->topic}");
        $this->info("Field: {$project->field_of_study}");
        $this->info("Type: {$project->type}");

        $this->info('Generating outline...');
        $success = $outlineService->generateProjectOutline($project);

        if ($success) {
            $this->info('✅ Outline generated successfully!');

            // Display the generated outline
            $project->refresh();
            $outlines = $project->outlines()->with('sections')->get();

            foreach ($outlines as $outline) {
                $this->info("\nChapter {$outline->chapter_number}: {$outline->chapter_title}");
                $this->info("Target: {$outline->target_word_count} words, Threshold: {$outline->completion_threshold}%");
                $this->info("Description: {$outline->description}");

                foreach ($outline->sections as $section) {
                    $this->line("  {$section->section_number} {$section->section_title} ({$section->target_word_count} words)");
                    $this->line("    → {$section->section_description}");
                }
            }

            return 0;
        } else {
            $this->error('❌ Failed to generate outline');

            return 1;
        }
    }
}
