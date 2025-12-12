<?php

namespace App\Services\PromptSystem\Templates;

use App\Models\Project;
use App\Services\PromptSystem\ContentRequirements;

abstract class BasePromptTemplate implements PromptTemplateInterface
{
    protected int $priority = 0;

    protected array $supportedChapterTypes = [
        'introduction',
        'literature_review',
        'methodology',
        'results',
        'discussion',
        'conclusion',
    ];

    /**
     * Get the base system prompt - can be extended by child classes
     */
    public function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert academic writer specializing in generating comprehensive, publication-ready academic content.

CORE PRINCIPLES:
1. Write in formal academic third-person style - NEVER use "I", "we", "my", "our"
2. Use phrases like "this study", "the research", "the analysis", "the findings"
3. Generate detailed, substantive content that meets word count requirements
4. Include proper academic citations in APA format
5. Create well-structured sections with clear headings

FORMATTING RULES:
- Use numbered section headings (e.g., 3.1, 3.2, 3.1.1)
- Never use "&" - always write "and"
- Use bullet points (•) not dashes (-) for lists
- Spell out chapter numbers: "CHAPTER ONE" not "CHAPTER 1"

CONTENT QUALITY:
- Each section must be comprehensive and well-developed
- Provide specific examples, evidence, and explanations
- Ensure logical flow between paragraphs and sections
- Include transitions between major sections
PROMPT;
    }

    /**
     * Build the chapter prompt - combines base structure with specific requirements
     */
    public function buildChapterPrompt(Project $project, int $chapterNumber, ContentRequirements $requirements): string
    {
        $prompt = $this->buildProjectContext($project, $chapterNumber);
        $prompt .= $this->buildChapterTypeInstructions($chapterNumber);
        $prompt .= $this->buildTableInstructions($requirements);
        $prompt .= $this->buildDiagramInstructions($requirements);
        $prompt .= $this->buildCalculationInstructions($requirements);
        $prompt .= $this->buildCodeInstructions($requirements);
        $prompt .= $this->buildPlaceholderInstructions($requirements);
        $prompt .= $this->buildFormattingInstructions($project, $chapterNumber);

        return $prompt;
    }

    /**
     * Build project context section
     */
    protected function buildProjectContext(Project $project, int $chapterNumber): string
    {
        $targetWordCount = $this->getTargetWordCount($project, $chapterNumber);

        return <<<CONTEXT

PROJECT CONTEXT:
- Topic: {$project->topic}
- Faculty: {$project->faculty}
- Department: {$project->department}
- Course: {$project->course}
- Field of Study: {$project->field_of_study}
- Academic Level: {$project->type}
- University: {$project->university}

CHAPTER REQUIREMENTS:
- Chapter Number: {$chapterNumber}
- Target Word Count: {$targetWordCount} words (MANDATORY - do not stop early)

CONTEXT;
    }

    /**
     * Build chapter type specific instructions
     */
    protected function buildChapterTypeInstructions(int $chapterNumber): string
    {
        $chapterType = $this->detectChapterType($chapterNumber);

        return match ($chapterType) {
            'introduction' => $this->getIntroductionInstructions(),
            'literature_review' => $this->getLiteratureReviewInstructions(),
            'methodology' => $this->getMethodologyInstructions(),
            'results' => $this->getResultsInstructions(),
            'discussion' => $this->getDiscussionInstructions(),
            'conclusion' => $this->getConclusionInstructions(),
            default => $this->getGenericChapterInstructions(),
        };
    }

    /**
     * Build table generation instructions
     */
    protected function buildTableInstructions(ContentRequirements $requirements): string
    {
        $tables = $requirements->getTables();
        if (empty($tables)) {
            return '';
        }

        $instructions = "\n\nTABLE REQUIREMENTS:\n";
        $instructions .= "This chapter MUST include the following tables:\n\n";

        foreach ($tables as $index => $table) {
            $tableNum = $index + 1;
            $required = ($table['required'] ?? false) ? '(REQUIRED)' : '(Recommended)';
            $instructions .= "Table {$tableNum}: {$table['type']} {$required}\n";
            $instructions .= "- Description: {$table['description']}\n";

            if ($table['mock_data'] ?? false) {
                $instructions .= "- Generate realistic SAMPLE DATA with clear warning: '⚠️ THIS IS SAMPLE DATA - Replace with your actual data'\n";
                $instructions .= "- Include step-by-step instructions for collecting real data\n";
            }

            if (! empty($table['columns'])) {
                $instructions .= '- Required columns: '.implode(', ', $table['columns'])."\n";
            }

            $instructions .= "\n";
        }

        return $instructions;
    }

    /**
     * Build diagram generation instructions
     */
    protected function buildDiagramInstructions(ContentRequirements $requirements): string
    {
        $diagrams = $requirements->getDiagrams();
        if (empty($diagrams)) {
            return '';
        }

        $instructions = "\n\nDIAGRAM REQUIREMENTS:\n";

        foreach ($diagrams as $diagram) {
            $required = ($diagram['required'] ?? false) ? '(REQUIRED)' : '(Recommended)';

            if ($diagram['can_generate'] ?? false) {
                $instructions .= "\n{$diagram['type']} {$required} - Generate using Mermaid syntax:\n";
                $instructions .= "```mermaid\n{$diagram['format']}\n```\n";
            } else {
                $instructions .= "\n{$diagram['type']} {$required} - Create PLACEHOLDER with instructions:\n";
                $instructions .= "- What to show: {$diagram['description']}\n";
                $instructions .= "- Recommended tool: {$diagram['tool']}\n";
                $instructions .= "- Include step-by-step creation guide\n";
            }
        }

        return $instructions;
    }

    /**
     * Build calculation instructions
     */
    protected function buildCalculationInstructions(ContentRequirements $requirements): string
    {
        if (! $requirements->requiresCalculations()) {
            return '';
        }

        $calculations = $requirements->calculations;

        $instructions = "\n\nCALCULATION REQUIREMENTS:\n";
        $instructions .= "Show all calculations with step-by-step workings:\n\n";
        $instructions .= "FORMAT FOR EACH CALCULATION:\n";
        $instructions .= "1. State the formula\n";
        $instructions .= "2. Define all variables with values\n";
        $instructions .= "3. Show substitution step\n";
        $instructions .= "4. Show arithmetic operations\n";
        $instructions .= "5. State final result with units\n";
        $instructions .= "6. Explain what the result means\n\n";

        if (! empty($calculations['types'])) {
            $instructions .= 'Required calculation types: '.implode(', ', $calculations['types'])."\n";
        }

        return $instructions;
    }

    /**
     * Build code generation instructions
     */
    protected function buildCodeInstructions(ContentRequirements $requirements): string
    {
        if (! $requirements->requiresCode()) {
            return '';
        }

        $code = $requirements->code;
        $language = $code['language'] ?? 'relevant programming language';

        $instructions = "\n\nCODE REQUIREMENTS:\n";
        $instructions .= "Include {$language} code with:\n";
        $instructions .= "- Proper syntax highlighting\n";
        $instructions .= "- Inline comments explaining logic\n";
        $instructions .= "- Function/method documentation\n";
        $instructions .= "- Working, realistic code (not pseudocode)\n\n";

        if (! empty($code['snippets'])) {
            $instructions .= "Required code sections:\n";
            foreach ($code['snippets'] as $snippet) {
                $instructions .= "- {$snippet}\n";
            }
        }

        return $instructions;
    }

    /**
     * Build placeholder instructions
     */
    protected function buildPlaceholderInstructions(ContentRequirements $requirements): string
    {
        $placeholders = $requirements->placeholders;
        if (empty($placeholders)) {
            return '';
        }

        $instructions = "\n\nPLACEHOLDER REQUIREMENTS:\n";
        $instructions .= "For content that cannot be AI-generated, create detailed placeholders:\n\n";
        $instructions .= "FORMAT:\n";
        $instructions .= "[FIGURE X.X: Title]\n";
        $instructions .= "⚠️ THIS REQUIRES [TYPE] THAT YOU MUST CREATE\n\n";
        $instructions .= "📋 WHAT TO SHOW:\n";
        $instructions .= "• [Specific details]\n\n";
        $instructions .= "🛠️ RECOMMENDED TOOLS:\n";
        $instructions .= "• [Tool name] - [URL] - [Best for]\n\n";
        $instructions .= "📐 STEP-BY-STEP GUIDE:\n";
        $instructions .= "1. [First step]\n";
        $instructions .= "2. [Second step]\n";
        $instructions .= "...\n\n";

        return $instructions;
    }

    /**
     * Build formatting instructions
     */
    protected function buildFormattingInstructions(Project $project, int $chapterNumber): string
    {
        return <<<FORMAT_INSTRUCTIONS

FORMATTING INSTRUCTIONS:
- Use section numbering: {$chapterNumber}.1, {$chapterNumber}.2, {$chapterNumber}.1.1, etc.
- Format headings as: '{$chapterNumber}.1 Section Title'
- Use proper academic table format with captions
- Reference all tables and figures in the text
- Use APA citation format: (Author, Year)

CRITICAL REMINDERS:
- Write in THIRD PERSON only
- Never use "&" - write "and"
- Use bullet points (•) not dashes (-)
- Generate comprehensive content to meet word count
- Include realistic sample data with replacement instructions

FORMAT_INSTRUCTIONS;
    }

    /**
     * Get target word count for chapter
     */
    protected function getTargetWordCount(Project $project, int $chapterNumber): int
    {
        // Default word counts by chapter type
        $defaults = [
            1 => 2500,  // Introduction
            2 => 5000,  // Literature Review
            3 => 3500,  // Methodology
            4 => 4000,  // Results
            5 => 3000,  // Discussion/Conclusion
        ];

        return $defaults[$chapterNumber] ?? 3000;
    }

    /**
     * Detect chapter type from number
     */
    protected function detectChapterType(int $chapterNumber): string
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
     * Get introduction chapter instructions
     */
    protected function getIntroductionInstructions(): string
    {
        return <<<'INTRO'

CHAPTER TYPE: INTRODUCTION

REQUIRED SECTIONS:
1.1 Background of the Study
    - Broad context narrowing to specific topic
    - Recent statistics and trends
    - Relevance to the field

1.2 Statement of the Problem
    - Clear problem definition
    - Evidence of the problem
    - Gap in current knowledge

1.3 Research Objectives
    - General objective
    - Specific objectives (3-5, using action verbs)

1.4 Research Questions/Hypotheses
    - Aligned with objectives
    - Clear and answerable

1.5 Significance of the Study
    - Theoretical contribution
    - Practical implications
    - Beneficiaries

1.6 Scope and Delimitations
    - What is covered
    - What is NOT covered
    - Justification for boundaries

INTRO;
    }

    /**
     * Get literature review instructions
     */
    protected function getLiteratureReviewInstructions(): string
    {
        return <<<'LITREV'

CHAPTER TYPE: LITERATURE REVIEW

REQUIRED SECTIONS:
2.1 Conceptual Review
    - Key concepts defined
    - Theoretical underpinnings

2.2 Theoretical Framework
    - Relevant theories explained
    - Application to current study

2.3 Empirical Review
    - Previous studies reviewed
    - Organized thematically (NOT chronologically)
    - Critical analysis, not just description
    - Show relationships between studies

2.4 Research Gap
    - What is missing in literature
    - How this study addresses the gap

2.5 Conceptual Framework (if applicable)
    - Visual model of relationships
    - Explanation of framework

CITATION REQUIREMENTS:
- Minimum 40-60 recent sources
- Mix of theoretical and empirical
- Critical analysis of each source

LITREV;
    }

    /**
     * Get methodology instructions - to be overridden by faculty templates
     */
    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY

REQUIRED SECTIONS:
3.1 Research Design
    - Type of research design
    - Justification for choice

3.2 Population and Sample
    - Target population defined
    - Sampling technique
    - Sample size with justification

3.3 Data Collection
    - Instruments/tools used
    - Procedure for data collection

3.4 Data Analysis
    - Analysis techniques
    - Software used (if any)

3.5 Ethical Considerations
    - Consent procedures
    - Confidentiality measures

METHOD;
    }

    /**
     * Get results instructions
     */
    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS/FINDINGS

REQUIRED SECTIONS:
4.1 Data Presentation
    - Organized by research question/objective
    - Tables and figures with proper captions

4.2 Data Analysis
    - Statistical analysis (if quantitative)
    - Thematic analysis (if qualitative)

4.3 Interpretation of Findings
    - What the results mean
    - Comparison with objectives

TABLE REQUIREMENTS:
- Include proper table numbering (Table 4.1, 4.2, etc.)
- Reference all tables in the text
- Generate sample data with clear replacement instructions

RESULTS;
    }

    /**
     * Get discussion instructions
     */
    protected function getDiscussionInstructions(): string
    {
        return <<<'DISCUSSION'

CHAPTER TYPE: DISCUSSION

REQUIRED SECTIONS:
5.1 Summary of Findings
    - Key findings restated

5.2 Discussion of Findings
    - Compare with literature review
    - Explain agreements/disagreements
    - Provide possible explanations

5.3 Implications
    - Theoretical implications
    - Practical implications

5.4 Limitations
    - Study limitations acknowledged
    - How they affect interpretation

DISCUSSION;
    }

    /**
     * Get conclusion instructions
     */
    protected function getConclusionInstructions(): string
    {
        return <<<'CONCLUSION'

CHAPTER TYPE: CONCLUSION

REQUIRED SECTIONS:
5.X Conclusion
    - Brief summary of the study
    - Key findings highlighted
    - Research questions answered

5.X Recommendations
    - Practical recommendations
    - Policy recommendations (if applicable)
    - Recommendations for future research

CONCLUSION;
    }

    /**
     * Get generic chapter instructions
     */
    protected function getGenericChapterInstructions(): string
    {
        return "\n\nProvide comprehensive content for this chapter following standard academic structure.\n";
    }

    /**
     * Default table requirements - to be overridden by child classes
     */
    public function getTableRequirements(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default diagram requirements - to be overridden by child classes
     */
    public function getDiagramRequirements(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default calculation requirements - to be overridden by child classes
     */
    public function getCalculationRequirements(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default code requirements - to be overridden by child classes
     */
    public function getCodeRequirements(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default placeholder rules - to be overridden by child classes
     */
    public function getPlaceholderRules(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default tool recommendations - to be overridden by child classes
     */
    public function getRecommendedTools(): array
    {
        return [];
    }

    /**
     * Get template priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Check if template supports chapter type
     */
    public function supportsChapterType(string $chapterType): bool
    {
        return in_array($chapterType, $this->supportedChapterTypes);
    }
}
