<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestExportFunctionality extends Command
{
    protected $signature = 'export:test {project_slug?} {--user=} {--chapters=}';

    protected $description = 'Test Word document export functionality';

    public function __construct(
        protected ExportService $exportService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('🧪 Starting Export Functionality Test...');

        // Get project
        $projectSlug = $this->argument('project_slug');
        $userId = $this->option('user');

        if ($projectSlug) {
            $project = Project::where('slug', $projectSlug)->first();
        } else {
            // Get first project with content
            $query = Project::whereHas('chapters', function ($q) {
                $q->whereNotNull('content')->where('content', '!=', '');
            });

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $project = $query->first();
        }

        if (! $project) {
            $this->error('No project found with content to test export.');

            return 1;
        }

        $this->info("📁 Testing export for project: {$project->title}");
        $this->info("   Slug: {$project->slug}");
        $this->info("   Owner: {$project->user->name}");

        // Load chapters
        $chapters = $project->chapters()->orderBy('chapter_number')->get();
        $chaptersWithContent = $chapters->filter(fn ($ch) => ! empty($ch->content));

        $this->info("📄 Chapters: {$chapters->count()} total, {$chaptersWithContent->count()} with content");

        if ($chaptersWithContent->isEmpty()) {
            $this->error('No chapters with content found. Cannot test export.');

            return 1;
        }

        $this->newLine();

        // Test 1: Single Chapter Export
        $this->testSingleChapterExport($project, $chaptersWithContent->first());

        // Test 2: Multiple Chapters Export
        if ($chaptersWithContent->count() > 1) {
            $this->testMultipleChaptersExport($project, $chaptersWithContent);
        }

        // Test 3: Full Project Export
        $this->testFullProjectExport($project);

        $this->newLine();
        $this->info('✅ Export tests completed!');

        return 0;
    }

    private function testSingleChapterExport($project, $chapter)
    {
        $this->info('🔧 Test 1: Single Chapter Export');
        $this->info("   Testing Chapter {$chapter->chapter_number}: {$chapter->title}");

        try {
            $startTime = microtime(true);
            $filename = $this->exportService->exportChapterToWord($project, $chapter);
            $duration = round(microtime(true) - $startTime, 2);

            if (file_exists($filename)) {
                $filesize = $this->formatBytes(filesize($filename));
                $this->info("   ✓ Success! File created: {$filesize} in {$duration}s");

                // Test file validity
                if ($this->validateWordFile($filename)) {
                    $this->info('   ✓ File structure validated');
                } else {
                    $this->warn('   ⚠ File may be corrupted');
                }

                // Clean up test file
                unlink($filename);
            } else {
                $this->error('   ✗ Failed: File not created');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error: '.$e->getMessage());
            Log::error('Test single chapter export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function testMultipleChaptersExport($project, $chaptersWithContent)
    {
        $this->info('🔧 Test 2: Multiple Chapters Export');

        // Get chapter numbers to test
        $chapterNumbers = $chaptersWithContent->take(3)->pluck('chapter_number')->toArray();
        $this->info('   Testing chapters: '.implode(', ', $chapterNumbers));

        try {
            $startTime = microtime(true);
            $filename = $this->exportService->exportMultipleChaptersToWord($project, $chapterNumbers);
            $duration = round(microtime(true) - $startTime, 2);

            if (file_exists($filename)) {
                $filesize = $this->formatBytes(filesize($filename));
                $this->info("   ✓ Success! File created: {$filesize} in {$duration}s");

                // Test file validity
                if ($this->validateWordFile($filename)) {
                    $this->info('   ✓ File structure validated');
                } else {
                    $this->warn('   ⚠ File may be corrupted');
                }

                // Clean up test file
                unlink($filename);
            } else {
                $this->error('   ✗ Failed: File not created');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error: '.$e->getMessage());
            Log::error('Test multiple chapters export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function testFullProjectExport($project)
    {
        $this->info('🔧 Test 3: Full Project Export');

        try {
            $startTime = microtime(true);
            $filename = $this->exportService->exportToWord($project);
            $duration = round(microtime(true) - $startTime, 2);

            if (file_exists($filename)) {
                $filesize = $this->formatBytes(filesize($filename));
                $this->info("   ✓ Success! File created: {$filesize} in {$duration}s");

                // Test file validity
                if ($this->validateWordFile($filename)) {
                    $this->info('   ✓ File structure validated');
                } else {
                    $this->warn('   ⚠ File may be corrupted');
                }

                // Clean up test file
                unlink($filename);
            } else {
                $this->error('   ✗ Failed: File not created');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error: '.$e->getMessage());
            Log::error('Test full project export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function validateWordFile($filename): bool
    {
        try {
            // Check if it's a valid ZIP (Word files are ZIP archives)
            $zip = new \ZipArchive;
            $result = $zip->open($filename);

            if ($result === true) {
                // Check for required Word document structure
                $hasContentTypes = false;
                $hasDocument = false;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if ($name === '[Content_Types].xml') {
                        $hasContentTypes = true;
                    }
                    if (strpos($name, 'word/document.xml') !== false) {
                        $hasDocument = true;
                    }
                }

                $zip->close();

                return $hasContentTypes && $hasDocument;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
