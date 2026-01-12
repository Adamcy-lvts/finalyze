<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class LawTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


LAW-SPECIFIC GUIDELINES:

LEGAL RESEARCH STANDARDS:
- Use proper legal citation format (OSCOLA, Bluebook, or local format)
- Reference primary sources (statutes, cases, regulations)
- Include secondary sources (journals, treatises, commentaries)
- Distinguish between binding and persuasive authority

LEGAL ANALYSIS:
- Apply legal reasoning and precedent analysis
- Discuss ratio decidendi and obiter dicta
- Consider jurisdictional differences
- Address policy implications

STATUTORY INTERPRETATION:
- Quote relevant statutory provisions
- Apply rules of interpretation
- Consider legislative intent
- Discuss judicial interpretation

CASE LAW ANALYSIS:
- Brief relevant cases with material facts
- Analyze court reasoning
- Discuss application to research topic
- Note any dissenting opinions of significance
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (LAW)

REQUIRED SECTIONS:

3.1 Research Approach
    - Doctrinal/black-letter law approach
    - Comparative legal method
    - Socio-legal approach (if applicable)
    - Justification for approach

3.2 Sources of Law
    - Primary sources:
        * Legislation (Acts, Regulations)
        * Case law (Court decisions)
        * Constitutional provisions
    - Secondary sources:
        * Legal journals and articles
        * Textbooks and treatises
        * Law reform reports

3.3 Jurisdiction
    - Jurisdiction(s) covered
    - Justification for jurisdictional scope
    - Comparative elements (if applicable)

3.4 Legal Research Method
    - Database searches conducted
    - Keywords and search terms used
    - Case selection criteria
    - Legislative history research

3.5 Analytical Framework
    - Legal doctrines applied
    - Theoretical perspective
    - Criteria for analysis

3.6 Limitations
    - Scope limitations
    - Access to sources
    - Currency of law

CRITICAL: END CHAPTER WITH REFERENCES SECTION
- After all content sections, include a "References" section
- List ALL sources cited in this chapter in APA 7th edition format
- Sort alphabetically by author's last name

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: ANALYSIS/FINDINGS (LAW)

REQUIRED SECTIONS:

4.1 Legislative Framework
    - Overview of relevant legislation
    - Analysis of key provisions
    - Legislative intent and purpose
    - Amendments and developments

4.2 Case Law Analysis
    For each major case/line of cases:
    - Case citation and brief facts
    - Legal issues addressed
    - Court's reasoning and decision
    - Significance to research topic

4.3 Doctrinal Analysis
    - Application of legal principles
    - Synthesis of authorities
    - Identification of legal gaps or conflicts

4.4 Comparative Analysis (if applicable)
    - Comparison across jurisdictions
    - Common approaches
    - Divergent approaches
    - Lessons for local jurisdiction

4.5 Critical Analysis
    - Strengths of current legal framework
    - Weaknesses and gaps
    - Reform proposals in literature
    - Original analysis and contribution

4.6 Summary of Findings
    - Key legal findings
    - Implications for law and policy

CITATION FORMAT:
Use proper legal citation throughout:
- Statutes: [Short Title] [Year] [Jurisdiction] (if applicable)
- Cases: [Party v Party] [Year] [Court] [Citation]
- Include paragraph/section numbers where relevant

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
                    'type' => 'legal_sources',
                    'required' => false,
                    'mock_data' => false,
                    'description' => 'Summary of legal sources consulted',
                    'columns' => ['Source Type', 'Source', 'Jurisdiction', 'Relevance'],
                ],
            ],
            4 => [
                [
                    'type' => 'case_summary',
                    'required' => false,
                    'mock_data' => false,
                    'description' => 'Summary of key cases analyzed',
                    'columns' => ['Case Name', 'Year', 'Court', 'Key Issue', 'Holding'],
                ],
                [
                    'type' => 'comparative_analysis',
                    'required' => false,
                    'mock_data' => false,
                    'description' => 'Comparative legal analysis',
                    'columns' => ['Issue', 'Jurisdiction A', 'Jurisdiction B', 'Analysis'],
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
                    'type' => 'legal_framework',
                    'required' => false,
                    'can_generate' => true,
                    'description' => 'Legal framework hierarchy',
                    'format' => "graph TD\n    A[Constitution] --> B[Primary Legislation]\n    B --> C[Secondary Legislation]\n    C --> D[Case Law]\n    D --> E[Application to Topic]",
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'legal_databases' => [
                ['name' => 'LexisNexis', 'url' => 'lexisnexis.com', 'cost' => 'Institutional', 'best_for' => 'Case law research'],
                ['name' => 'Westlaw', 'url' => 'westlaw.com', 'cost' => 'Institutional', 'best_for' => 'Comprehensive legal research'],
                ['name' => 'HeinOnline', 'url' => 'heinonline.org', 'cost' => 'Institutional', 'best_for' => 'Legal journals'],
            ],
            'citation' => [
                ['name' => 'Zotero', 'url' => 'zotero.org', 'cost' => 'Free', 'best_for' => 'Reference management'],
            ],
        ];
    }
}
