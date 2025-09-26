<?php

namespace App\Console\Commands;

use App\Http\Controllers\DefenseController;
use App\Models\Project;
use App\Services\AIContentGenerator;
use Illuminate\Console\Command;

class TestDefenseQuestions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'defense:test {project_id=24}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test defense questions generation and parsing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projectId = $this->argument('project_id');
        $project = Project::find($projectId);

        if (! $project) {
            $this->error("Project {$projectId} not found");

            return;
        }

        $this->info("Testing defense questions for project: {$project->title}");

        // Create a controller instance
        $aiGenerator = app(AIContentGenerator::class);
        $controller = new DefenseController($aiGenerator);

        // Use reflection to call the private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generateQuestionsSynchronously');
        $method->setAccessible(true);

        try {
            $questions = $method->invoke($controller, $project, 5, 3);

            $this->info('Generated questions count: '.$questions->count());

            foreach ($questions as $question) {
                $this->line('---');
                $this->line('Question: '.$question->question);
                $this->line('Answer: '.substr($question->suggested_answer, 0, 100).'...');
                $this->line('Difficulty: '.$question->difficulty);
                $this->line('Category: '.$question->category);
            }

        } catch (\Exception $e) {
            $this->error('Error generating questions: '.$e->getMessage());
            $this->line($e->getTraceAsString());
        }
    }
}
