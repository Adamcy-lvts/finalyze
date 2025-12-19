<?php

namespace App\Services\PromptSystem;

use App\Models\Project;
use App\Services\PromptSystem\Templates\PromptTemplateInterface;

class PromptBuilder
{
    public function __construct(
        private MockDataGenerator $mockDataGenerator,
        private PlaceholderInstructionBuilder $placeholderBuilder
    ) {}

    /**
     * Build the complete prompt for chapter generation
     */
    public function build(
        Project $project,
        int $chapterNumber,
        PromptTemplateInterface $template,
        ContentRequirements $requirements
    ): string {
        $prompt = '';

        // 1. Add chapter-specific instructions (includes project context in templates)
        $prompt .= $template->buildChapterPrompt($project, $chapterNumber, $requirements);

        // 2. Add table generation instructions with mock data
        $prompt .= $this->buildTableSection($requirements, $project, $chapterNumber);

        // 3. Add diagram generation/placeholder instructions
        $prompt .= $this->buildDiagramSection($requirements, $project, $chapterNumber);

        // 4. Add calculation instructions
        $prompt .= $this->buildCalculationSection($requirements);

        // 5. Add code generation instructions
        $prompt .= $this->buildCodeSection($requirements);

        // 6. Add tool recommendations
        $prompt .= $this->buildToolRecommendations($requirements);

        // 7. Add final formatting reminders
        $prompt .= $this->buildFinalReminders($project, $chapterNumber);

        return $prompt;
    }

    /**
     * Build table section with mock data instructions
     */
    private function buildTableSection(ContentRequirements $requirements, Project $project, int $chapterNumber): string
    {
        $tables = $requirements->getTables();
        if (empty($tables)) {
            return '';
        }

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "📊 TABLE REQUIREMENTS FOR THIS CHAPTER\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $section .= 'This chapter MUST include '.count($tables)." table(s):\n\n";

        $tableNumber = 1;
        foreach ($tables as $table) {
            $prefix = $chapterNumber.'.'.$tableNumber;
            $required = ($table['required'] ?? false) ? '✅ REQUIRED' : '📌 Recommended';

            $section .= "┌─────────────────────────────────────────────────────────────┐\n";
            $section .= "│ Table {$prefix}: {$table['type']} [{$required}]\n";
            $section .= "├─────────────────────────────────────────────────────────────┤\n";
            $section .= "│ Purpose: {$table['description']}\n";

            if (! empty($table['columns'])) {
                $section .= '│ Columns: '.implode(' | ', $table['columns'])."\n";
            }

            if ($table['mock_data'] ?? false) {
                $section .= "│\n";
                $section .= "│ 📋 GENERATE SAMPLE DATA with this format:\n";

                // Get mock data structure from generator
                $mockData = $this->mockDataGenerator->generateTableStructure($table['type'], $project);
                if ($mockData) {
                    $section .= "│\n";
                    $section .= $this->formatMockDataInstructions($mockData, $prefix);
                }

                $section .= "│\n";
                $section .= "│ ⚠️ ADD THIS WARNING BELOW THE TABLE:\n";
                $section .= "│ \"⚠️ THIS IS SAMPLE DATA - Replace with your actual data\"\n";
                $section .= "│\n";
                $section .= "│ 📝 DATA COLLECTION INSTRUCTIONS:\n";

                $instructions = $table['instructions'] ?? $this->getDefaultDataInstructions($table['type']);
                foreach ($instructions as $i => $instruction) {
                    $num = $i + 1;
                    $section .= "│ {$num}. {$instruction}\n";
                }
            }

            $section .= "└─────────────────────────────────────────────────────────────┘\n\n";
            $tableNumber++;
        }

        return $section;
    }

    /**
     * Build diagram section with placeholders
     */
    private function buildDiagramSection(ContentRequirements $requirements, Project $project, int $chapterNumber): string
    {
        $diagrams = $requirements->getDiagrams();
        if (empty($diagrams)) {
            return '';
        }

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "📐 DIAGRAM/FIGURE REQUIREMENTS\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $figureNumber = 1;
        foreach ($diagrams as $diagram) {
            $prefix = $chapterNumber.'.'.$figureNumber;

            if ($diagram['can_generate'] ?? false) {
                // AI can generate this diagram (e.g., Mermaid flowchart)
                $section .= "Figure {$prefix}: {$diagram['type']} (Generate using Mermaid)\n";
                $section .= "Generate this diagram using Mermaid syntax:\n";
                $section .= "```mermaid\n";
                $section .= "{$diagram['format']}\n";
                $section .= "```\n\n";
            } else {
                // AI cannot generate - create placeholder with instructions
                $section .= $this->placeholderBuilder->build(
                    $diagram['type'],
                    $project,
                    [
                        'figure_number' => $prefix,
                        'description' => $diagram['description'],
                        'tool' => $diagram['tool'] ?? null,
                        'components' => $diagram['components'] ?? [],
                    ]
                );
                $section .= "\n\n";
            }

            $figureNumber++;
        }

        return $section;
    }

    /**
     * Build calculation instructions section
     */
    private function buildCalculationSection(ContentRequirements $requirements): string
    {
        if (! $requirements->requiresCalculations()) {
            return '';
        }

        $calculations = $requirements->calculations;

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "🧮 CALCULATION REQUIREMENTS\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $section .= "Show ALL calculations with step-by-step workings:\n\n";

        $section .= "FORMAT FOR EACH CALCULATION:\n";
        $section .= "┌─────────────────────────────────────────────────────────────┐\n";
        $section .= "│ 1. State the formula:                                       │\n";
        $section .= "│    Formula: [Name] = [Mathematical expression]              │\n";
        $section .= "│                                                             │\n";
        $section .= "│ 2. Define variables:                                        │\n";
        $section .= "│    Where: [Variable] = [Value] [Units]                      │\n";
        $section .= "│                                                             │\n";
        $section .= "│ 3. Substitute values:                                       │\n";
        $section .= "│    [Formula with numbers]                                   │\n";
        $section .= "│                                                             │\n";
        $section .= "│ 4. Calculate:                                               │\n";
        $section .= "│    = [Step-by-step arithmetic]                              │\n";
        $section .= "│    = [Final result] [Units]                                 │\n";
        $section .= "│                                                             │\n";
        $section .= "│ 5. Interpret:                                               │\n";
        $section .= "│    This means [practical interpretation]                    │\n";
        $section .= "└─────────────────────────────────────────────────────────────┘\n\n";

        if (! empty($calculations['types'])) {
            $section .= "Required calculations:\n";
            foreach ($calculations['types'] as $type) {
                $section .= "• {$type}\n";
            }
            $section .= "\n";
        }

        if (! empty($calculations['examples'])) {
            $section .= "Example calculations to include:\n";
            foreach ($calculations['examples'] as $example) {
                $section .= "• {$example}\n";
            }
        }

        return $section;
    }

    /**
     * Build code generation section
     */
    private function buildCodeSection(ContentRequirements $requirements): string
    {
        if (! $requirements->requiresCode()) {
            return '';
        }

        $code = $requirements->code;
        $language = $code['language'] ?? 'appropriate programming language';

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "💻 CODE REQUIREMENTS\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $section .= "Include {$language} code with:\n";
        $section .= "• Proper syntax highlighting using ```{$language}\n";
        $section .= "• Inline comments explaining logic\n";
        $section .= "• Function/method documentation\n";
        $section .= "• Working, realistic code (NOT pseudocode)\n\n";

        if (! empty($code['snippets'])) {
            $section .= "Required code sections:\n";
            foreach ($code['snippets'] as $snippet) {
                $section .= "• {$snippet}\n";
            }
        }

        return $section;
    }

    /**
     * Build tool recommendations section
     */
    private function buildToolRecommendations(ContentRequirements $requirements): string
    {
        $tools = $requirements->tools;
        if (empty($tools)) {
            return '';
        }

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "🛠️ RECOMMENDED TOOLS FOR USER\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $section .= "When creating placeholders, recommend these tools:\n\n";

        foreach ($tools as $category => $toolList) {
            $section .= ucfirst($category).":\n";
            foreach ($toolList as $tool) {
                $cost = $tool['cost'] ?? 'Paid';
                $url = $tool['url'] ?? '';
                $section .= "• {$tool['name']} ({$cost})";
                if ($url) {
                    $section .= " - {$url}";
                }
                if (! empty($tool['best_for'])) {
                    $section .= "\n  Best for: {$tool['best_for']}";
                }
                $section .= "\n";
            }
            $section .= "\n";
        }

        return $section;
    }

    /**
     * Build final reminders
     */
    private function buildFinalReminders(Project $project, int $chapterNumber): string
    {
        $targetWords = $this->getTargetWordCount($project, $chapterNumber);

        return <<<REMINDERS


FINAL OUTPUT RULES:
1. WORD COUNT: Write at least {$targetWords} words. Do not stop early.
2. FORMAT: Use section numbers like {$chapterNumber}.1, {$chapterNumber}.2, {$chapterNumber}.1.1
3. TABLES/FIGURES: Reference each table/figure in the text before it appears.
4. NO META: Do not output instructions, warnings, or any AI commentary; output only the chapter content.
5. NO REFERENCES LIST: Do not add a "References" section at the end of this chapter.

REMINDERS;
    }

    /**
     * Format mock data instructions for embedding in prompt
     */
    private function formatMockDataInstructions(array $mockData, string $tablePrefix): string
    {
        $formatted = "│ Example structure:\n";
        $formatted .= "│ Table {$tablePrefix}: {$mockData['title']}\n";

        if (! empty($mockData['headers'])) {
            $formatted .= '│ Headers: '.implode(' | ', $mockData['headers'])."\n";
        }

        return $formatted;
    }

    /**
     * Get default data collection instructions by table type
     */
    private function getDefaultDataInstructions(string $tableType): array
    {
        return match ($tableType) {
            'sample_demographics', 'demographics' => [
                'Collect demographic data from your questionnaire Section A',
                'Enter data into SPSS or Excel',
                'Calculate frequency and percentage for each category',
                'Total should equal your sample size (N)',
            ],
            'test_results', 'performance_metrics' => [
                'Set up your test environment and equipment',
                'Run each test at least 3 times for consistency',
                'Record measurements with proper units',
                'Calculate average values and deviations',
            ],
            'component_specification', 'components' => [
                'List all components from your circuit design',
                'Get specifications from component datasheets',
                'Check current prices from local suppliers',
                'Include quantity and calculate total cost',
            ],
            'hypothesis_test', 'statistical_analysis' => [
                'Enter your survey data into SPSS',
                'Run the appropriate statistical test',
                'Record test statistic, df, and p-value',
                'State whether hypothesis is supported',
            ],
            default => [
                'Collect the required data from your research',
                'Organize data in the format shown',
                'Verify all values are accurate',
                'Update the table with your actual data',
            ],
        };
    }

    /**
     * Get target word count for chapter
     */
    private function getTargetWordCount(Project $project, int $chapterNumber): int
    {
        // Could be enhanced to use faculty structure service
        $defaults = [
            1 => 2500,
            2 => 5000,
            3 => 3500,
            4 => 4000,
            5 => 3000,
        ];

        return $defaults[$chapterNumber] ?? 3000;
    }
}
