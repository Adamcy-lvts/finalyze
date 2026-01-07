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
6. Do not include AI/meta commentary (no "Note:", no explanations about the prompt, no "as an AI model")
7. Do not add a "References" section at the end of a chapter (references appear only at the end of the full project)
8. If a verified sources list is provided, citations must be limited to that list only

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

STRICT CITATION POLICY:
- Use in-text citations only (no References/Bibliography section inside a chapter)
- If a "Verified Sources" block is provided in the user prompt, you may ONLY cite those sources
- Use ONLY the format (FirstAuthorLastName, Year) - e.g., (Smith, 2020) or (Johnson, 2019)
- Do NOT use "et al." and do NOT invent author names or years
- If you cannot support a sentence with an allowed in-text citation, add: [Citation needed]

CITATION FORMAT FOR REFERENCE TRACKING:
- Every in-text citation MUST match this exact pattern: (AuthorLastName, Year)
- For multiple authors, use: (Smith and Johnson, 2020) - never use "&"
- Citations are automatically extracted for the References section
- Consistent formatting ensures proper reference collection during export
- Each citation used will appear in the final References section (sorted alphabetically)
PROMPT;
    }

    /**
     * Build the chapter prompt - combines base structure with specific requirements
     */
    public function buildChapterPrompt(Project $project, int $chapterNumber, ContentRequirements $requirements): string
    {
        $prompt = $this->buildProjectContext($project, $chapterNumber);
        $prompt .= $this->buildChapterTypeInstructions($project, $chapterNumber);
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
        $metaWordCount = $project->getAttribute('chapter_word_count');
        $targetWordCount = is_numeric($metaWordCount) ? (int) $metaWordCount : $this->getTargetWordCount($project, $chapterNumber);
        $chapterTitle = $project->getAttribute('chapter_title');
        $chapterTitleLine = '';
        if (is_string($chapterTitle) && trim($chapterTitle) !== '') {
            $chapterTitleLine = "- Chapter Title: {$chapterTitle}\n";
        }

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
{$chapterTitleLine}- Target Word Count: {$targetWordCount} words (MANDATORY - do not stop early)

CONTEXT;
    }

    /**
     * Build chapter type specific instructions
     */
    protected function buildChapterTypeInstructions(Project $project, int $chapterNumber): string
    {
        $metaType = $project->getAttribute('chapter_type');
        $chapterType = is_string($metaType) && trim($metaType) !== '' ? $metaType : $this->detectChapterType($chapterNumber);

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
                $instructions .= "- Generate realistic sample data and label it clearly as sample data to be replaced\n";
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
        $instructions .= "(To be created by the student)\n\n";
        $instructions .= "WHAT TO SHOW:\n";
        $instructions .= "• [Specific details]\n\n";
        $instructions .= "RECOMMENDED TOOLS:\n";
        $instructions .= "• [Tool name] - [URL] - [Best for]\n\n";
        $instructions .= "STEP-BY-STEP GUIDE:\n";
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

CITATION REMINDERS:
- Use consistent (AuthorLastName, Year) format for all citations
- Distribute citations across sections (don't cluster all in one paragraph)
- Each unique citation will be collected for the References section

CRITICAL REMINDERS:
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

2.5 Conceptual Framework (MANDATORY)
    - Visual model of relationships (Mermaid diagram or clearly marked figure instructions)
    - Constructs, indicators, and hypothesized relationships
    - Justification for each hypothesized link grounded in the literature

NON-NEGOTIABLE LITERATURE REVIEW REQUIREMENTS:
1) Synthesis Matrix / Summary Table (MANDATORY)
   - Include a synthesis matrix/table with the columns:
     Author (Allowed in-text citation), Country/Context, Method/Design, Key Findings, Limitations, Relevance to the study context
   - Populate the table ONLY using the verified sources provided in the prompt.
   - In the "Relevance to the study context" column, explicitly tie each study to the project's domain and setting (e.g., the project's country/region/industry if specified).

2) Thematic Comparison + Contradictions (MANDATORY)
   - Organize the empirical review into clear themes/sub-themes.
   - For EACH theme, explicitly compare at least 3 studies (agreements, differences, and contradictions).
   - Identify why contradictions may exist (method, sample, context, measurement, timeframe) and what that implies for the current study’s context.
   - Close each theme with a short synthesis paragraph that states: (i) what is known, (ii) what is uncertain/contested, and (iii) what the current study will assume/test/build.
   - Ensure each theme is substantial (typically 2–4 paragraphs per theme) and not a single short paragraph.

3) Conceptual Framework Section (MANDATORY)
   - Present constructs and measurable indicators (a small table is acceptable).
   - Provide hypotheses/propositions (H1, H2, ...) with brief justification for each link.
   - Include a Mermaid diagram if possible; otherwise include a clearly marked placeholder figure with step-by-step creation instructions.
   - Ensure every hypothesized link is supported by at least one cited study from the verified list; otherwise add [Citation needed] after the justification sentence.
   - Ensure the themes in 2.3 map directly to the constructs/links in 2.5 (no disconnected literature themes).

CITATION BEHAVIOR:
- Use frequent in-text citations, but ONLY from the verified sources list (strict whitelist).
- Use ONLY (FirstAuthorLastName, Year) allowed citation strings when provided; otherwise add [Citation needed].
 - Do not reuse the same single citation repeatedly; distribute citations across themes and studies where relevant.

OUTPUT RULES:
- Do NOT add a "References" section at the end of this chapter
- Do NOT include any "Note:" or meta commentary; output only academic chapter content

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
