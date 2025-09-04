<?php

namespace App\Console\Commands;

use App\Services\AIContentGenerator;
use Illuminate\Console\Command;

class TestIntelligentModelSelection extends Command
{
    protected $signature = 'ai:test-selection {--dry-run : Show selection without generating content}';

    protected $description = 'Test intelligent model selection for different academic contexts';

    public function handle(AIContentGenerator $generator)
    {
        $this->info('🧠 Testing Intelligent Model Selection...');
        $this->newLine();

        // Test scenarios with different academic contexts
        $testCases = [
            [
                'name' => 'PhD Computer Science',
                'context' => [
                    'field_of_study' => 'Computer Science',
                    'academic_level' => 'phd',
                    'faculty' => 'Engineering',
                ],
                'expected_model' => 'gpt-4o',
            ],
            [
                'name' => 'Masters Engineering',
                'context' => [
                    'field_of_study' => 'Mechanical Engineering',
                    'academic_level' => 'masters',
                    'faculty' => 'Engineering',
                ],
                'expected_model' => 'gpt-4o',
            ],
            [
                'name' => 'Undergraduate Arts',
                'context' => [
                    'field_of_study' => 'English Literature',
                    'academic_level' => 'undergraduate',
                    'faculty' => 'Arts',
                ],
                'expected_model' => 'gpt-4o-mini',
            ],
            [
                'name' => 'Masters AI/ML (High Complexity)',
                'context' => [
                    'field_of_study' => 'Artificial Intelligence',
                    'academic_level' => 'masters',
                    'faculty' => 'Computer Science',
                ],
                'expected_model' => 'gpt-4o',
            ],
            [
                'name' => 'Undergraduate STEM',
                'context' => [
                    'field_of_study' => 'Physics',
                    'academic_level' => 'undergraduate',
                    'faculty' => 'Science',
                ],
                'expected_model' => 'gpt-4o', // High quality due to Science faculty
            ],
        ];

        foreach ($testCases as $testCase) {
            $this->info("Testing: {$testCase['name']}");

            // Use reflection to access private method for testing
            $reflection = new \ReflectionClass($generator);
            $method = $reflection->getMethod('getTopicGenerationOptions');
            $method->setAccessible(true);

            $options = $method->invoke($generator, $testCase['context']);
            $selectedModel = $options['model'];

            $status = $selectedModel === $testCase['expected_model'] ? '✅' : '❌';

            $this->line("  Context: {$testCase['context']['field_of_study']} | {$testCase['context']['academic_level']} | {$testCase['context']['faculty']}");
            $this->line("  Selected Model: {$selectedModel}");
            $this->line("  Expected Model: {$testCase['expected_model']}");
            $this->line("  Result: {$status}");
            $this->line("  Temperature: {$options['temperature']}");
            $this->line("  Max Tokens: {$options['max_tokens']}");
            $this->newLine();
        }

        if (! $this->option('dry-run')) {
            $this->info('🚀 Testing actual topic generation with sample context...');

            $sampleContext = [
                'field_of_study' => 'Computer Science',
                'academic_level' => 'masters',
                'faculty' => 'Engineering',
            ];

            $this->info('Generating topics with intelligent selection...');
            $this->line('Academic Context: Masters in Computer Science, Faculty of Engineering');

            $prompt = "Generate 5 research topics for a master's thesis in Computer Science focusing on machine learning applications.";

            $generatedContent = '';
            foreach ($generator->generateTopicsOptimized($prompt, $sampleContext) as $chunk) {
                $generatedContent .= $chunk;
                $this->line($chunk, null, 'v');
            }

            $this->newLine();
            $this->info('✨ Content generation completed successfully!');
        }

        $this->newLine();
        $this->info('🎯 Model selection testing completed!');
    }
}
