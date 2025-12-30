<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use App\Services\AIContentGenerator;
use App\Services\Defense\DefenseContentExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefenseContentExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_project_data_and_tables(): void
    {
        $user = User::factory()->create([
            'word_balance' => 5000,
        ]);

        $project = Project::factory()
            ->for($user)
            ->create([
                'title' => 'Sample Project',
                'topic' => 'Sample Topic',
                'field_of_study' => 'Computer Science',
            ]);

        $html = '<h1>Introduction</h1>'
            .'<p>'.str_repeat('word ', 210).'</p>'
            .'<p>Sample with n = 200 and 85% response rate.</p>'
            .'<table><tr><th>Metric</th><th>Value</th></tr><tr><td>A</td><td>10</td></tr></table>';

        Chapter::factory()
            ->for($project)
            ->create([
                'chapter_number' => 1,
                'title' => 'Introduction',
                'content' => $html,
            ]);

        $fakeGenerator = new class
        {
            public function generate(string $prompt, array $options = []): string
            {
                return json_encode([
                    'problem_statement' => 'Sample problem',
                    'specific_objectives' => ['Objective 1'],
                ]);
            }
        };

        $this->app->instance(AIContentGenerator::class, $fakeGenerator);

        $extractor = $this->app->make(DefenseContentExtractor::class);
        $data = $extractor->extractFromProject($project);

        $this->assertSame('Sample Project', $data['project_meta']['title']);
        $this->assertCount(1, $data['chapters']);
        $chapterData = $data['chapters'][0];
        $this->assertSame('introduction', $chapterData['type']);
        $this->assertArrayHasKey('tables_extracted', $chapterData['extracted_data']);
        $this->assertNotEmpty($chapterData['extracted_data']['tables_extracted']);
    }
}
