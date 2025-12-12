<?php

namespace App\Services\Topics;

use App\Models\Project;
use Illuminate\Support\Facades\Log;

class TopicParser
{
    public function parseAndValidate(string $generatedContent, Project $project): array
    {
        preg_match_all('/^\d+\.\s*(.+)$/m', $generatedContent, $matches);

        if (empty($matches[1])) {
            $lines = array_filter(array_map('trim', explode("\n", $generatedContent)));
            $topics = array_slice($lines, 0, 10);
        } else {
            $topics = $matches[1];
        }

        $cleanedTopics = [];
        foreach ($topics as $topic) {
            $cleaned = trim($topic);
            $cleaned = preg_replace('/^\d+\.\s*/', '', $cleaned);

            if (strlen($cleaned) >= 20 && strlen($cleaned) <= 200) {
                $cleanedTopics[] = $cleaned;
            }
        }

        if (count($cleanedTopics) < 5) {
            Log::warning('AI generated insufficient topics', [
                'generated_count' => count($cleanedTopics),
                'raw_content' => $generatedContent,
            ]);

            return $this->generateEnhancedMockTopics($project);
        }

        return array_slice($cleanedTopics, 0, 10);
    }

    public function generateEnhancedMockTopics(Project $project): array
    {
        $field = $project->field_of_study;
        $university = $project->universityRelation?->name;

        $templates = [
            'Development and Implementation of {technology} Solutions for {field} Applications in Nigerian Context',
            'Comparative Analysis of {field} Practices: A Study of {university} and Similar Institutions',
            'Machine Learning Applications in {field}: Opportunities and Challenges in West African Universities',
            'Digital Transformation Impact on {field} Education and Practice in Nigeria',
            'Design and Development of Mobile-Based {field} Management System for Nigerian Students',
            'Blockchain Technology Integration in {field}: Security and Efficiency Enhancement Study',
            'Internet of Things (IoT) Applications for {field} Monitoring and Optimization',
            'Cloud Computing Solutions for {field} Data Management in Resource-Constrained Environments',
            'Artificial Intelligence-Powered {field} Decision Support System Development',
            'Cybersecurity Framework Development for {field} Information Systems in Nigerian Institutions',
        ];

        $technologies = ['AI-Powered', 'Cloud-Based', 'Mobile-First', 'IoT-Enabled', 'Blockchain-Secured'];

        $topics = [];
        foreach (array_slice($templates, 0, 8) as $template) {
            $topic = str_replace(
                ['{field}', '{university}', '{technology}'],
                [$field, $university, $technologies[array_rand($technologies)]],
                $template
            );
            $topics[] = $topic;
        }

        return $topics;
    }
}
