<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can export project to word document', function () {
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
        'content' => '<h2>1.1 Background</h2><p>This is the background section.</p>',
        'order' => 1,
    ]);

    $response = $this->actingAs($user)
        ->get(route('projects.export-word', $project));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

it('requires authentication to export project', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.export-word', $project));

    $response->assertRedirect(route('login'));
});

it('prevents exporting other users projects', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)
        ->get(route('projects.export-word', $project));

    $response->assertForbidden();
});

it('can export individual chapter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 1,
        'title' => 'Introduction',
        'content' => '<p>Test content</p>',
    ]);

    $response = $this->actingAs($user)
        ->get(route('chapters.export-word', [$project, $chapter->chapter_number]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

it('can export multiple chapters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 1,
        'content' => '<p>Chapter 1 content</p>',
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 2,
        'content' => '<p>Chapter 2 content</p>',
    ]);

    $response = $this->actingAs($user)
        ->post(route('chapters.export-multiple', $project), [
            'chapters' => [1, 2],
        ]);

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});
