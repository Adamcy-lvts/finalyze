<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class BusinessTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


BUSINESS/MANAGEMENT-SPECIFIC GUIDELINES:

RESEARCH ORIENTATION:
- Balance theoretical frameworks with practical applications
- Reference established business theories (Porter, Kotler, etc.)
- Use industry-relevant terminology and metrics
- Connect findings to business implications

DATA ANALYSIS:
- Present financial data with appropriate precision
- Use business metrics (ROI, NPV, market share)
- Include trend analysis where relevant
- Compare with industry benchmarks

STRATEGIC ANALYSIS:
- Apply relevant frameworks (SWOT, PESTLE, Porter's Five Forces)
- Consider stakeholder perspectives
- Discuss competitive implications
- Provide actionable recommendations

MARKET RESEARCH:
- Segment analysis and profiling
- Consumer behavior insights
- Competitive landscape assessment
- Market trend identification
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (BUSINESS/MANAGEMENT)

REQUIRED SECTIONS:

3.1 Research Design
    - Research approach (quantitative, qualitative, mixed methods)
    - Research philosophy (positivist, interpretivist)
    - Justification for chosen design

3.2 Population and Sample
    - Target population description
    - Sampling frame
    - Sampling technique
    - Sample size and justification

3.3 Data Sources
    - Primary data sources
    - Secondary data sources
    - Data collection timeline

3.4 Research Instrument
    - Questionnaire design
    - Interview guide (if qualitative)
    - Scales used (Likert, semantic differential)
    - Pilot testing results

3.5 Validity and Reliability
    - Content validity
    - Construct validity
    - Reliability testing (Cronbach's Alpha)

3.6 Data Collection Procedure
    - Administration method
    - Response rate strategies
    - Follow-up procedures

3.7 Data Analysis Techniques
    - Descriptive statistics
    - Inferential statistics
    - Qualitative analysis methods (if applicable)
    - Software used

3.8 Ethical Considerations
    - Informed consent
    - Confidentiality
    - Organizational approval

REQUIRED TABLES:
- Table 3.1: Operationalization of Variables
- Table 3.2: Reliability Test Results
- Table 3.3: Data Analysis Plan by Objective

CRITICAL: END CHAPTER WITH REFERENCES SECTION
- After all content sections, include a "References" section
- List ALL sources cited in this chapter in APA 7th edition format
- Sort alphabetically by author's last name

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS (BUSINESS/MANAGEMENT)

REQUIRED SECTIONS:

4.1 Response Rate and Data Screening
    - Response rate analysis
    - Data cleaning procedures
    - Missing data handling

4.2 Respondent Profile
    - Demographic characteristics
    - Organizational characteristics (if B2B)
    - Descriptive analysis

4.3 Analysis of Research Objectives

    4.3.1 Objective 1: [Title]
        - Descriptive statistics
        - Data presentation (tables, charts)
        - Interpretation

    4.3.2 Objective 2: [Title]
        - Analysis results
        - Interpretation

    (Continue for all objectives)

4.4 Hypothesis Testing
    - Statistical test results
    - Decision on each hypothesis
    - Interpretation

4.5 Additional Findings
    - Correlation analysis
    - Regression analysis (if applicable)
    - Factor analysis (if applicable)

4.6 Summary of Findings
    - Key findings by objective
    - Implications for business practice

REQUIRED TABLES:
- Table 4.1: Demographic Profile of Respondents
- Table 4.2: Descriptive Statistics for Study Variables
- Table 4.3: Correlation Matrix
- Table 4.4: Hypothesis Test Results

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
                    'type' => 'variable_operationalization',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Operationalization of study variables',
                    'columns' => ['Variable', 'Type', 'Measurement', 'Scale', 'Source'],
                ],
                [
                    'type' => 'reliability_test',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Reliability test results',
                    'columns' => ['Variable/Scale', 'No. of Items', "Cronbach's Alpha", 'Interpretation'],
                ],
            ],
            4 => [
                [
                    'type' => 'respondent_profile',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Demographic profile of respondents',
                    'columns' => ['Characteristic', 'Category', 'Frequency', 'Percentage (%)'],
                ],
                [
                    'type' => 'descriptive_statistics',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Descriptive statistics for study variables',
                    'columns' => ['Variable', 'N', 'Mean', 'Std. Dev', 'Interpretation'],
                ],
                [
                    'type' => 'correlation_matrix',
                    'required' => false,
                    'mock_data' => true,
                    'description' => 'Correlation matrix of key variables',
                    'columns' => ['Variable', 'V1', 'V2', 'V3', 'V4'],
                ],
                [
                    'type' => 'hypothesis_test',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Hypothesis testing results',
                    'columns' => ['Hypothesis', 'Test', 'Statistic', 'p-value', 'Decision'],
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
                    'type' => 'conceptual_framework',
                    'required' => true,
                    'can_generate' => true,
                    'description' => 'Conceptual framework diagram',
                    'format' => "graph LR\n    subgraph Independent Variables\n        IV1[Marketing Strategy]\n        IV2[Product Quality]\n        IV3[Customer Service]\n    end\n    subgraph Dependent Variable\n        DV[Customer Satisfaction]\n    end\n    IV1 --> DV\n    IV2 --> DV\n    IV3 --> DV",
                ],
            ],
            4 => [
                [
                    'type' => 'results_model',
                    'required' => false,
                    'can_generate' => true,
                    'description' => 'Research model with results',
                    'format' => "graph LR\n    IV1[Variable 1] -->|β=0.XX, p<0.05| DV[Outcome]\n    IV2[Variable 2] -->|β=0.XX, p<0.05| DV\n    IV3[Variable 3] -->|β=0.XX, ns| DV",
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'analysis' => [
                ['name' => 'IBM SPSS Statistics', 'url' => 'ibm.com/spss', 'cost' => 'Academic License', 'best_for' => 'Survey analysis'],
                ['name' => 'Microsoft Excel', 'url' => 'microsoft.com/excel', 'cost' => 'Paid', 'best_for' => 'Financial modeling'],
                ['name' => 'Tableau', 'url' => 'tableau.com', 'cost' => 'Free (Public)', 'best_for' => 'Data visualization'],
            ],
            'survey' => [
                ['name' => 'Google Forms', 'url' => 'forms.google.com', 'cost' => 'Free', 'best_for' => 'Online surveys'],
                ['name' => 'Qualtrics', 'url' => 'qualtrics.com', 'cost' => 'Academic License', 'best_for' => 'Advanced surveys'],
            ],
        ];
    }
}
