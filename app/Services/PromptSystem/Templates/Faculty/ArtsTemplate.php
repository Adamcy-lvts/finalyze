<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class ArtsTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


ARTS/HUMANITIES-SPECIFIC GUIDELINES:

SCHOLARLY APPROACH:
- Engage with primary sources and original texts
- Apply appropriate theoretical frameworks
- Use discipline-specific terminology correctly
- Demonstrate critical analysis and interpretation

WRITING STYLE:
- Balance theoretical discussion with textual analysis
- Develop sophisticated arguments with supporting evidence
- Acknowledge multiple perspectives and interpretations
- Use appropriate citation style (MLA, Chicago, or as required)

CULTURAL CONTEXT:
- Consider historical and cultural contexts
- Discuss social implications and relevance
- Address representation and diversity issues
- Connect to broader intellectual traditions

CREATIVE ANALYSIS:
- Analyze form, structure, and technique
- Discuss aesthetic elements and their effects
- Interpret symbolic and thematic content
- Consider artist/author intent and reception
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (ARTS/HUMANITIES)

REQUIRED SECTIONS:

3.1 Research Approach
    - Qualitative research design
    - Theoretical framework
    - Analytical perspective

3.2 Research Method
    - Method selection justification
    - Textual/content analysis approach
    - Historical method (if applicable)
    - Ethnographic method (if applicable)

3.3 Data/Source Collection
    - Primary sources identified
    - Secondary sources
    - Selection criteria
    - Access and availability

3.4 Sampling Strategy
    - Purposive sampling justification
    - Sample selection criteria
    - Representativeness considerations

3.5 Data Analysis Techniques
    - Thematic analysis
    - Discourse analysis
    - Semiotics analysis (if applicable)
    - Hermeneutic approach (if applicable)

3.6 Validity and Reliability
    - Credibility measures
    - Triangulation approach
    - Researcher reflexivity

3.7 Ethical Considerations
    - Copyright and fair use
    - Cultural sensitivity
    - Representation ethics

CRITICAL: END CHAPTER WITH REFERENCES SECTION
- After all content sections, include a "References" section
- List ALL sources cited in this chapter in APA 7th edition format
- Sort alphabetically by author's last name

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: FINDINGS/ANALYSIS (ARTS/HUMANITIES)

REQUIRED SECTIONS:

4.1 Overview of Analysis
    - Summary of analytical approach
    - Organization of findings

4.2 Thematic Analysis
    For each major theme:
    - Theme identification and description
    - Supporting evidence from sources
    - Interpretation and significance
    - Connection to theoretical framework

4.3 Textual/Visual Analysis
    - Close reading/viewing of key works
    - Analysis of form and content
    - Contextual interpretation

4.4 Comparative Analysis (if applicable)
    - Comparison across texts/works
    - Patterns and variations
    - Synthesis of findings

4.5 Discussion of Findings
    - Relationship to research questions
    - Connection to existing scholarship
    - New insights and contributions

PRESENTATION FORMAT:
- Present findings thematically, not source-by-source
- Include relevant quotations with proper citations
- Integrate analysis with evidence throughout
- Use subheadings to organize themes

CRITICAL: END CHAPTER WITH REFERENCES SECTION
- After all content sections, include a "References" section
- List ALL sources cited in this chapter in APA 7th edition format
- Sort alphabetically by author's last name

RESULTS;
    }

    public function getTableRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            3 => [
                [
                    'type' => 'source_analysis',
                    'required' => false,
                    'mock_data' => false,
                    'description' => 'Primary sources for analysis',
                    'columns' => ['Source', 'Type', 'Date/Period', 'Relevance'],
                ],
            ],
            4 => [
                [
                    'type' => 'thematic_summary',
                    'required' => false,
                    'mock_data' => false,
                    'description' => 'Summary of themes identified',
                    'columns' => ['Theme', 'Description', 'Key Sources', 'Significance'],
                ],
            ],
            default => [],
        };
    }

    public function getDiagramRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            2 => [
                [
                    'type' => 'theoretical_framework',
                    'required' => false,
                    'can_generate' => true,
                    'description' => 'Theoretical framework diagram',
                    'format' => "graph TD\n    A[Main Theory] --> B[Concept 1]\n    A --> C[Concept 2]\n    A --> D[Concept 3]\n    B --> E[Application]\n    C --> E\n    D --> E",
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'analysis' => [
                ['name' => 'NVivo', 'url' => 'qsrinternational.com', 'cost' => 'Academic License', 'best_for' => 'Qualitative analysis'],
                ['name' => 'ATLAS.ti', 'url' => 'atlasti.com', 'cost' => 'Academic License', 'best_for' => 'Text analysis'],
            ],
            'reference' => [
                ['name' => 'Zotero', 'url' => 'zotero.org', 'cost' => 'Free', 'best_for' => 'Reference management'],
                ['name' => 'JSTOR', 'url' => 'jstor.org', 'cost' => 'Institutional Access', 'best_for' => 'Academic sources'],
            ],
        ];
    }
}
