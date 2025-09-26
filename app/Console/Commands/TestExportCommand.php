<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ExportService;
use Illuminate\Console\Command;

class TestExportCommand extends Command
{
    protected $signature = 'export:test {project_id} {chapter_number?}';

    protected $description = 'Test chapter export functionality';

    public function __construct(
        private ExportService $exportService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $projectId = $this->argument('project_id');
        $chapterNumber = $this->argument('chapter_number');

        $project = Project::with('chapters')->find($projectId);

        if (! $project) {
            $this->error("Project with ID {$projectId} not found.");

            return 1;
        }

        $this->info("Testing export for project: {$project->title}");

        try {
            if ($chapterNumber) {
                $chapter = $project->chapters()->where('chapter_number', $chapterNumber)->first();
                if (! $chapter) {
                    $this->error("Chapter {$chapterNumber} not found.");

                    return 1;
                }

                $this->info("Exporting chapter {$chapterNumber}: {$chapter->title}");
                $this->info('Content length: '.strlen($chapter->content ?? ''));

                // Show first 200 chars of content to see formatting
                $preview = substr($chapter->content ?? '', 0, 200);
                $this->line('Content preview: '.$preview.'...');

                $filename = $this->exportService->exportChapterToWord($project, $chapter);
                $this->info('✅ Chapter export successful: '.basename($filename));
                $this->info('File size: '.filesize($filename).' bytes');
            } else {
                $this->info('Exporting entire project...');
                $filename = $this->exportService->exportToWord($project);
                $this->info('✅ Project export successful: '.basename($filename));
                $this->info('File size: '.filesize($filename).' bytes');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Export failed: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return 1;
        }
    }
}
