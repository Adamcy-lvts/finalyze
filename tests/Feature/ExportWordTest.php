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

    // Create some test chapters with rich content
    Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 1,
        'title' => 'Introduction',
        'content' => '<h2>1.1 Background</h2><p>This is the background section with <strong>bold text</strong> and <em>italic text</em>.</p><ul><li>Item 1</li><li>Item 2</li></ul>',
        'order' => 1,
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.project.word', $project));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

it('requires authentication to export project', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('export.project.word', $project));

    $response->assertRedirect(route('login'));
});

it('prevents exporting other users projects', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 1,
        'content' => '<p>Test content</p>',
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.project.word', $project));

    $response->assertForbidden();
});

it('can export individual chapter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 1,
        'title' => 'Introduction',
        'content' => '<p>Test content with <strong>formatting</strong> and <table><tr><td>table</td></tr></table></p>',
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.chapter.word', ['project' => $project, 'chapterNumber' => $chapter->chapter_number]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

it('can export multiple chapters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 1,
        'content' => '<p>Chapter 1 content with <code>code blocks</code></p>',
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 2,
        'content' => '<p>Chapter 2 content with <blockquote>quotes</blockquote></p>',
    ]);

    $response = $this->actingAs($user)
        ->post(route('export.chapters.word', $project), [
            'chapters' => [1, 2],
        ]);

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

it('handles export of chapter with complex tiptap content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Complex Tiptap HTML with various formatting
    $complexContent = <<<'HTML'
<h2>Section Title</h2>
<p>This is a paragraph with <strong>bold</strong>, <em>italic</em>, and <code>inline code</code>.</p>
<ul>
    <li>First bullet point</li>
    <li>Second bullet point with <strong>formatting</strong></li>
</ul>
<pre><code class="language-php">function test() {
    return true;
}</code></pre>
<table>
    <tr>
        <th>Header 1</th>
        <th>Header 2</th>
    </tr>
    <tr>
        <td>Data 1</td>
        <td>Data 2</td>
    </tr>
</table>
<blockquote>
    <p>This is a quote with citation.</p>
</blockquote>
HTML;

    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'chapter_number' => 1,
        'title' => 'Complex Chapter',
        'content' => $complexContent,
    ]);

    $response = $this->actingAs($user)
        ->get(route('export.chapter.word', ['project' => $project, 'chapterNumber' => 1]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('wordprocessingml.document');
});
