<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can export project to word document', function () {
    // Create a user and project with chapters
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Project',
        'topic' => 'Testing PHPWord Export',
        'abstract' => 'This is a test abstract for the project.',
        'status' => 'writing',
    ]);

    // Create some test chapters
    Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 1,
        'title' => 'Introduction',
        'content' => '<h2>1.1 Background</h2><p>This is the background section.</p><h2>1.2 Problem Statement</h2><p>This describes the problem.</p>',
        'order' => 1,
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 2,
        'title' => 'Literature Review',
        'content' => '<p>This is the literature review content with some <strong>bold</strong> text.</p><ul><li>First point</li><li>Second point</li></ul>',
        'order' => 2,
    ]);

    $exportService = new ExportService;
    $filename = $exportService->exportToWord($project);

    // Assert file was created
    expect($filename)->toBeString();
    expect(file_exists($filename))->toBeTrue();
    expect(pathinfo($filename, PATHINFO_EXTENSION))->toBe('docx');

    // Clean up
    unlink($filename);
});

it('handles projects without chapters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Empty Project',
        'topic' => 'Testing Empty Project Export',
        'status' => 'setup',
        'topic_status' => 'topic_selection',
    ]);

    $exportService = new ExportService;
    $filename = $exportService->exportToWord($project);

    expect(file_exists($filename))->toBeTrue();

    // Clean up
    unlink($filename);
});

    it('creates exports directory if it does not exist', function () {
        // Remove the exports directory if it exists
        $exportDir = storage_path('app/exports');
        if (is_dir($exportDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($exportDir);
        }

    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Directory Test Project',
    ]);

    $exportService = new ExportService;
    $filename = $exportService->exportToWord($project);

    expect(is_dir($exportDir))->toBeTrue();
    expect(file_exists($filename))->toBeTrue();

    // Clean up
    unlink($filename);
});
