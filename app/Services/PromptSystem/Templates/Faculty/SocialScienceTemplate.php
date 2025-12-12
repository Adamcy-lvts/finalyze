<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class SocialScienceTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


SOCIAL SCIENCE-SPECIFIC GUIDELINES:

RESEARCH METHODOLOGY:
- Use appropriate research design terminology (descriptive, correlational, experimental)
- Clearly distinguish between qualitative and quantitative approaches
- Justify sampling techniques with statistical formulas
- Document instrument development and validation

STATISTICAL ANALYSIS:
- Use SPSS or equivalent for data analysis
- Report all statistical tests with complete notation (t, df, p, r, F)
- Include effect sizes where appropriate
- Use proper interpretation of significance levels

DATA PRESENTATION:
- Present demographic data with frequencies and percentages
- Use appropriate charts for categorical vs continuous data
- Include correlation matrices where relevant
- Present regression results in standard format

ETHICAL CONSIDERATIONS:
- Document informed consent procedures
- Address confidentiality and anonymity
- Discuss potential risks and benefits to participants
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (SOCIAL SCIENCE)

REQUIRED SECTIONS:

3.1 Research Design
    - Type of design (descriptive, correlational, experimental, etc.)
    - Justification for design choice
    - Variables identification (independent, dependent, moderating, mediating)

3.2 Population of the Study
    - Target population defined clearly
    - Characteristics of the population
    - Geographic location/scope

3.3 Sample and Sampling Technique
    - Sampling technique used (probability/non-probability)
    - Sample size determination with formula
    - Justification for sample size
    - Selection criteria (inclusion/exclusion)

    SAMPLE SIZE CALCULATION (use Yamane or similar):
    n = N / (1 + N(e)²)
    Where: n = sample size, N = population, e = margin of error (0.05)

3.4 Research Instrument
    - Description of instrument (questionnaire, interview guide, etc.)
    - Structure and sections of instrument
    - Rating scales used (Likert, semantic differential)
    - Source of instrument (adapted/self-developed)

3.5 Validity of Instrument
    - Face validity
    - Content validity
    - Construct validity (if applicable)
    - How validity was established

3.6 Reliability of Instrument
    - Reliability test conducted (Cronbach's Alpha)
    - Results of pilot study
    - Interpretation of reliability coefficients

3.7 Method of Data Collection
    - Step-by-step procedure
    - Duration of data collection
    - Response rate

3.8 Method of Data Analysis
    - Statistical tools for each objective/hypothesis
    - Software used (SPSS version)
    - Significance level adopted

3.9 Ethical Considerations
    - Institutional approval
    - Informed consent
    - Confidentiality measures
    - Right to withdraw

REQUIRED TABLES:
- Table 3.1: Operationalization of Variables
- Table 3.2: Sample Size Distribution (if stratified)
- Table 3.3: Reliability Test Results (Cronbach's Alpha)

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS (SOCIAL SCIENCE)

REQUIRED SECTIONS:

4.1 Response Rate
    - Questionnaires distributed vs returned
    - Valid responses
    - Response rate percentage

4.2 Demographic Characteristics of Respondents
    - Present demographics in table format
    - Include: Gender, Age, Education, Experience, etc.
    - Frequencies and percentages
    - Brief analysis of demographic profile

4.3 Presentation of Data According to Research Objectives
    Organize by research question/objective:

    4.3.1 Research Objective/Question 1
        - Descriptive statistics (Mean, SD)
        - Interpretation of findings

    4.3.2 Research Objective/Question 2
        - Statistical analysis results
        - Interpretation

    (Continue for all objectives)

4.4 Test of Hypotheses
    For each hypothesis:
    - State the null and alternative hypothesis
    - Present statistical test used
    - Show test results (t-value, df, p-value, r, R², etc.)
    - State decision (reject/fail to reject null hypothesis)
    - Interpret the result

    FORMAT:
    H₀: [Null hypothesis statement]
    H₁: [Alternative hypothesis statement]

    Table 4.X: [Test Name] Results
    [Statistical output table]

    Decision: Since p-value (0.XXX) < α (0.05), reject the null hypothesis
    Interpretation: There is a significant relationship/difference...

4.5 Summary of Findings
    - Key findings summarized
    - Relationship to objectives

REQUIRED TABLES:
- Table 4.1: Response Rate
- Table 4.2: Demographic Characteristics of Respondents
- Table 4.3: Descriptive Statistics for Study Variables
- Table 4.X: Hypothesis Test Results (for each hypothesis)

SAMPLE DATA:
Generate realistic sample data for:
- Demographics (typical academic population distribution)
- Likert scale responses (normally distributed, realistic means 3.2-4.1)
- Statistical test results (realistic t-values, p-values)

Mark ALL sample data: "THIS IS SAMPLE DATA - Replace with your actual data"

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
                    'columns' => ['Variable', 'Operational Definition', 'Indicators', 'Scale', 'Question Items'],
                ],
                [
                    'type' => 'reliability_test',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Reliability test results for each scale',
                    'columns' => ['Scale/Section', 'No. of Items', "Cronbach's Alpha", 'Interpretation'],
                    'instructions' => [
                        'Run reliability analysis in SPSS: Analyze > Scale > Reliability Analysis',
                        'Select items for each subscale',
                        'Record the Cronbach\'s Alpha coefficient',
                        'Interpretation: > 0.9 Excellent, > 0.8 Good, > 0.7 Acceptable',
                    ],
                ],
            ],
            4 => [
                [
                    'type' => 'response_rate',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Survey response rate analysis',
                    'columns' => ['Category', 'Distributed', 'Returned', 'Valid', 'Response Rate (%)'],
                ],
                [
                    'type' => 'sample_demographics',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Demographic characteristics of respondents',
                    'columns' => ['Variable', 'Category', 'Frequency', 'Percentage (%)'],
                    'instructions' => [
                        'Enter demographic data from questionnaire Section A into SPSS',
                        'Run frequency analysis: Analyze > Descriptive Statistics > Frequencies',
                        'Export frequency table',
                        'Calculate percentage: (frequency/total) × 100',
                    ],
                ],
                [
                    'type' => 'descriptive_statistics',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Descriptive statistics for study variables',
                    'columns' => ['Variable', 'N', 'Mean', 'Std. Deviation', 'Interpretation'],
                ],
                [
                    'type' => 'hypothesis_test',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Hypothesis testing results',
                    'columns' => ['Hypothesis', 'Test Used', 'Test Statistic', 'df', 'p-value', 'Decision'],
                    'instructions' => [
                        'Identify appropriate test for each hypothesis',
                        'For comparing means: Independent samples t-test or ANOVA',
                        'For relationships: Pearson correlation or regression',
                        'For categorical data: Chi-square test',
                        'Run test in SPSS and record output values',
                    ],
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
                    'description' => 'Conceptual framework showing variable relationships',
                    'format' => "graph LR\n    subgraph Independent Variables\n        IV1[Variable 1]\n        IV2[Variable 2]\n    end\n    subgraph Dependent Variable\n        DV[Outcome]\n    end\n    IV1 --> DV\n    IV2 --> DV",
                ],
            ],
            3 => [
                [
                    'type' => 'research_design_diagram',
                    'required' => false,
                    'can_generate' => true,
                    'description' => 'Visual representation of research design',
                    'format' => "flowchart TD\n    A[Population] --> B[Sampling]\n    B --> C[Sample]\n    C --> D[Data Collection]\n    D --> E[Data Analysis]\n    E --> F[Findings]",
                ],
            ],
            default => [],
        };
    }

    public function getCalculationRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            3 => [
                'required' => true,
                'types' => [
                    'Sample size (Yamane): n = N / (1 + Ne²)',
                    'Sample size (Cochran): n = (Z² × p × q) / e²',
                ],
                'examples' => [
                    'Calculate sample size from population',
                    'Show margin of error calculation',
                ],
            ],
            4 => [
                'required' => true,
                'types' => [
                    'Response rate: (Returned/Distributed) × 100',
                    'Percentage: (Frequency/Total) × 100',
                    'Mean interpretation against scale midpoint',
                    'Correlation coefficient interpretation',
                ],
                'examples' => [
                    'Calculate and interpret response rate',
                    'Interpret mean scores on Likert scale',
                    'Interpret correlation strength',
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'statistics' => [
                ['name' => 'IBM SPSS Statistics', 'url' => 'ibm.com/spss', 'cost' => 'Academic License', 'best_for' => 'Survey analysis'],
                ['name' => 'JASP', 'url' => 'jasp-stats.org', 'cost' => 'Free', 'best_for' => 'Free SPSS alternative'],
                ['name' => 'Jamovi', 'url' => 'jamovi.org', 'cost' => 'Free', 'best_for' => 'User-friendly statistics'],
            ],
            'survey' => [
                ['name' => 'Google Forms', 'url' => 'forms.google.com', 'cost' => 'Free', 'best_for' => 'Online surveys'],
                ['name' => 'Kobo Toolbox', 'url' => 'kobotoolbox.org', 'cost' => 'Free', 'best_for' => 'Field surveys'],
            ],
        ];
    }
}
