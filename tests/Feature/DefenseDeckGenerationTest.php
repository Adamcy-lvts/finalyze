<?php

namespace Tests\Feature;

use App\Jobs\GenerateDefenseDeckOutline;
use App\Models\Chapter;
use App\Models\DefenseSlideDeck;
use App\Models\Project;
use App\Models\User;
use App\Services\AIContentGenerator;
use App\Services\Defense\DefenseContentExtractor;
use App\Services\Defense\DefenseCreditService;
use App\Services\Defense\DefenseSlideDeckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefenseDeckGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_outline_with_extraction(): void
    {
        $user = User::factory()->create([
            'word_balance' => 2000,
        ]);

        $project = Project::factory()
            ->for($user)
            ->create([
                'title' => 'Generated Project',
                'topic' => 'Generated Topic',
                'field_of_study' => 'Business',
            ]);

        $content = '<p>'.str_repeat('word ', 210).'</p><p>n = 200 and 85%.</p>';

        Chapter::factory()
            ->for($project)
            ->create([
                'chapter_number' => 1,
                'title' => 'Introduction',
                'content' => $content,
            ]);

        $deck = DefenseSlideDeck::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'extraction_status' => 'pending',
            'ai_models' => [
                'outline' => 'gpt-4o',
                'extraction' => 'gpt-4o-mini',
            ],
        ]);

        $fakeGenerator = new class
        {
            public function generate(string $prompt, array $options = []): string
            {
                if (($options['model'] ?? '') === 'gpt-4o-mini') {
                    return json_encode([
                        'problem_statement' => 'Sample problem',
                        'specific_objectives' => ['Objective 1'],
                    ]);
                }

                return json_encode([
                    'slides' => [
                        [
                            'title' => 'Title Slide',
                            'bullets' => ['Bullet'],
                            'layout' => 'bullets',
                            'visuals' => '',
                            'speaker_notes' => '',
                            'charts' => [],
                            'tables' => [],
                        ],
                    ],
                ]);
            }
        };

        $this->app->instance(AIContentGenerator::class, $fakeGenerator);

        $job = new GenerateDefenseDeckOutline($deck->id);
        $job->handle(
            $fakeGenerator,
            $this->app->make(DefenseSlideDeckService::class),
            $this->app->make(DefenseContentExtractor::class),
            $this->app->make(DefenseCreditService::class)
        );

        $deck->refresh();

        $this->assertSame('outlined', $deck->status);
        $this->assertSame('extracted', $deck->extraction_status);
        $this->assertNotEmpty($deck->slides_json);
        $this->assertNotEmpty($deck->extraction_data);
    }
}
