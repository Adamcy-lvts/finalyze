<?php

namespace App\Services\PromptSystem;

use App\Models\Project;
use App\Services\PromptSystem\Requirements\DiagramRequirements;
use App\Services\PromptSystem\Requirements\TableRequirements;
use App\Services\PromptSystem\Requirements\ToolRecommendations;
use App\Services\PromptSystem\Templates\PromptTemplateInterface;

class ContentDecisionEngine
{
    public function __construct(
        private TableRequirements $tableRequirements,
        private DiagramRequirements $diagramRequirements,
        private ToolRecommendations $toolRecommendations
    ) {}

    /**
     * Analyze project and determine all content requirements
     */
    public function analyze(
        Project $project,
        int $chapterNumber,
        array $context,
        PromptTemplateInterface $template
    ): ContentRequirements {
        $metaType = $project->getAttribute('chapter_type');
        $chapterType = is_string($metaType) && trim($metaType) !== '' ? $metaType : $this->detectChapterType($chapterNumber);
        $projectType = $context['project_type'] ?? 'general';
        $faculty = $context['faculty'] ?? 'general';

        // Get requirements from each specialized class
        $tables = $this->determineTableRequirements($chapterType, $projectType, $faculty, $chapterNumber, $project, $template);
        $diagrams = $this->determineDiagramRequirements($chapterType, $projectType, $faculty, $chapterNumber, $project, $template);
        $calculations = $this->determineCalculationRequirements($chapterType, $projectType, $faculty);
        $code = $this->determineCodeRequirements($chapterType, $projectType, $faculty);
        $placeholders = $this->determinePlaceholderNeeds($chapterType, $projectType, $diagrams);
        $tools = $this->toolRecommendations->getToolsForContext($projectType, $faculty, $chapterType);

        return new ContentRequirements(
            tables: $tables,
            diagrams: $diagrams,
            calculations: $calculations,
            code: $code,
            mockData: $this->getMockDataConfig($tables),
            placeholders: $placeholders,
            tools: $tools,
            citations: $this->getCitationRequirements($chapterType, $faculty),
            formatting: $this->getFormattingRules($faculty)
        );
    }

    /**
     * Determine table requirements based on chapter type and context
     */
    private function determineTableRequirements(
        string $chapterType,
        string $projectType,
        string $faculty,
        int $chapterNumber,
        Project $project,
        PromptTemplateInterface $template
    ): array {
        // First get requirements from the specialized TableRequirements class
        $baseRequirements = $this->tableRequirements->getRequirements($chapterType, $projectType, $faculty);

        // Merge with template-specific requirements
        $templateRequirements = $template->getTableRequirements($chapterNumber);

        // Combine and deduplicate
        return $this->mergeRequirements($baseRequirements, $templateRequirements);
    }

    /**
     * Determine diagram requirements based on chapter type and context
     */
    private function determineDiagramRequirements(
        string $chapterType,
        string $projectType,
        string $faculty,
        int $chapterNumber,
        Project $project,
        PromptTemplateInterface $template
    ): array {
        // Get base diagram requirements
        $baseRequirements = $this->diagramRequirements->getRequirements($chapterType, $projectType, $faculty);

        // Merge with template requirements
        $templateRequirements = $template->getDiagramRequirements($chapterNumber);

        return $this->mergeRequirements($baseRequirements, $templateRequirements);
    }

    /**
     * Determine calculation requirements
     */
    private function determineCalculationRequirements(
        string $chapterType,
        string $projectType,
        string $faculty
    ): array {
        // Calculations are mainly needed in methodology and results chapters
        if (! in_array($chapterType, ['methodology', 'results'])) {
            return [];
        }

        $calculations = [
            'required' => false,
            'types' => [],
            'examples' => [],
        ];

        // Engineering projects
        if ($faculty === 'engineering') {
            $calculations['required'] = true;

            if ($projectType === 'hardware') {
                $calculations['types'] = [
                    'Power consumption (P = V × I)',
                    'Voltage divider (Vout = Vin × R2/(R1+R2))',
                    'Current limiting resistor',
                    'Efficiency calculation',
                ];
                $calculations['examples'] = [
                    'Calculate total power consumption of the circuit',
                    'Calculate resistor values for LED current limiting',
                    'Calculate voltage regulator output',
                ];
            } elseif ($projectType === 'software') {
                $calculations['types'] = [
                    'Time complexity analysis (Big O)',
                    'Space complexity',
                    'Performance metrics',
                ];
                $calculations['examples'] = [
                    'Calculate algorithm time complexity',
                    'Analyze database query performance',
                ];
            }
        }

        // Social science/Survey research
        if ($faculty === 'social_science' || $projectType === 'survey_research') {
            $calculations['required'] = $chapterType === 'results';
            $calculations['types'] = [
                'Sample size calculation (Yamane formula)',
                'Cronbach\'s Alpha for reliability',
                't-test for mean comparison',
                'Chi-square for association',
                'Correlation coefficient',
            ];
            $calculations['examples'] = [
                'Calculate sample size: n = N/(1+Ne²)',
                'Interpret Cronbach\'s Alpha values',
                'Calculate and interpret t-test results',
            ];
        }

        // Healthcare
        if ($faculty === 'healthcare') {
            $calculations['required'] = $chapterType === 'results';
            $calculations['types'] = [
                'BMI calculation',
                'Statistical significance (p-values)',
                'Effect size calculation',
                'Confidence intervals',
            ];
        }

        // Business
        if ($faculty === 'business') {
            $calculations['required'] = true;
            $calculations['types'] = [
                'ROI (Return on Investment)',
                'NPV (Net Present Value)',
                'Break-even analysis',
                'Growth rate calculations',
            ];
        }

        return $calculations;
    }

    /**
     * Determine code requirements
     */
    private function determineCodeRequirements(
        string $chapterType,
        string $projectType,
        string $faculty
    ): array {
        // Code is mainly needed in methodology and results for technical projects
        if (! in_array($chapterType, ['methodology', 'results'])) {
            return [];
        }

        // Only engineering/software projects typically need code
        if ($faculty !== 'engineering' || ! in_array($projectType, ['software', 'hardware'])) {
            return [];
        }

        $code = [
            'required' => true,
            'language' => 'auto',
            'snippets' => [],
        ];

        if ($projectType === 'software') {
            $code['language'] = 'php/javascript/python';
            $code['snippets'] = [
                'Core algorithm implementation',
                'Database connection/queries',
                'API endpoint handlers',
                'Key business logic functions',
            ];
        } elseif ($projectType === 'hardware') {
            $code['language'] = 'c/cpp/arduino';
            $code['snippets'] = [
                'Main program loop',
                'Sensor reading functions',
                'Output control functions',
                'Communication protocols',
            ];
        }

        return $code;
    }

    /**
     * Determine what content needs placeholders (can't be AI-generated)
     */
    private function determinePlaceholderNeeds(
        string $chapterType,
        string $projectType,
        array $diagrams
    ): array {
        $placeholders = [];

        // Filter diagrams that need placeholders
        foreach ($diagrams as $diagram) {
            if (! ($diagram['can_generate'] ?? false)) {
                $placeholders[] = [
                    'type' => $diagram['type'],
                    'description' => $diagram['description'],
                    'tool' => $diagram['tool'] ?? null,
                    'instructions_required' => true,
                ];
            }
        }

        // Screenshots are always placeholders
        if ($projectType === 'software' && in_array($chapterType, ['methodology', 'results'])) {
            $placeholders[] = [
                'type' => 'screenshot',
                'description' => 'Application screenshots showing user interface',
                'tool' => 'Screen capture tool',
                'instructions_required' => true,
            ];
        }

        return $placeholders;
    }

    /**
     * Get mock data configuration for tables
     */
    private function getMockDataConfig(array $tables): array
    {
        $mockDataTables = array_filter($tables, fn ($t) => $t['mock_data'] ?? false);

        return [
            'tables_needing_mock_data' => count($mockDataTables),
            'types' => array_column($mockDataTables, 'type'),
        ];
    }

    /**
     * Get citation requirements by chapter type
     */
    private function getCitationRequirements(string $chapterType, string $faculty): array
    {
        $minCitations = match ($chapterType) {
            'introduction' => ['min' => 10, 'max' => 20],
            'literature_review' => match ($faculty) {
                'science' => ['min' => 60, 'max' => 80],
                'engineering' => ['min' => 50, 'max' => 70],
                'social_science' => ['min' => 70, 'max' => 90],
                'healthcare' => ['min' => 50, 'max' => 70],
                'business' => ['min' => 60, 'max' => 80],
                default => ['min' => 50, 'max' => 70],
            },
            'methodology' => ['min' => 15, 'max' => 25],
            'results' => ['min' => 10, 'max' => 20],
            'discussion' => ['min' => 20, 'max' => 35],
            'conclusion' => ['min' => 5, 'max' => 15],
            default => ['min' => 10, 'max' => 20],
        };

        return [
            'min_citations' => $minCitations['min'],
            'max_citations' => $minCitations['max'],
            'format' => 'APA',
            'recency' => 'Last 5-7 years preferred',
        ];
    }

    /**
     * Get formatting rules by faculty
     */
    private function getFormattingRules(string $faculty): array
    {
        return [
            'citation_style' => 'APA',
            'third_person' => true,
            'no_ampersand' => true,
            'bullet_style' => '•',
            'section_numbering' => true,
            'table_format' => 'APA',
        ];
    }

    /**
     * Detect chapter type from number
     */
    private function detectChapterType(int $chapterNumber): string
    {
        return match ($chapterNumber) {
            1 => 'introduction',
            2 => 'literature_review',
            3 => 'methodology',
            4 => 'results',
            5 => 'discussion',
            default => 'general',
        };
    }

    /**
     * Convert chapter type to number
     */
    private function chapterTypeToNumber(string $chapterType): int
    {
        return match ($chapterType) {
            'introduction' => 1,
            'literature_review' => 2,
            'methodology' => 3,
            'results' => 4,
            'discussion' => 5,
            'conclusion' => 6,
            default => 1,
        };
    }

    /**
     * Merge two requirement arrays, avoiding duplicates
     */
    private function mergeRequirements(array $base, array $additional): array
    {
        $merged = $base;

        foreach ($additional as $item) {
            $exists = false;
            foreach ($merged as $existing) {
                if (($existing['type'] ?? '') === ($item['type'] ?? '')) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $merged[] = $item;
            }
        }

        return $merged;
    }
}
