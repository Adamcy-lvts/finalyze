<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class HealthcareTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


HEALTHCARE/NURSING-SPECIFIC GUIDELINES:

CLINICAL RESEARCH STANDARDS:
- Follow CONSORT guidelines for clinical trials
- Use standardized clinical terminology (ICD, SNOMED)
- Reference evidence-based practice guidelines
- Document ethical approval and informed consent

PATIENT DATA PRESENTATION:
- Maintain strict confidentiality in data presentation
- Use de-identified data in all tables and figures
- Present clinical outcomes with appropriate measures
- Include pre/post intervention comparisons

STATISTICAL ANALYSIS:
- Use appropriate clinical statistics (relative risk, odds ratio, NNT)
- Report confidence intervals for all estimates
- Include effect sizes for clinical significance
- Use survival analysis where appropriate

NURSING INTERVENTIONS:
- Describe interventions using standardized language
- Document care protocols clearly
- Reference nursing theories and models
- Evaluate outcomes using validated instruments
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (HEALTHCARE/NURSING)

REQUIRED SECTIONS:

3.1 Research Design
    - Study design (descriptive, quasi-experimental, RCT, etc.)
    - Rationale for design choice
    - Study setting description

3.2 Study Population
    - Target population
    - Inclusion criteria
    - Exclusion criteria
    - Recruitment strategy

3.3 Sample Size Determination
    - Sample size calculation with formula
    - Power analysis (if applicable)
    - Anticipated dropout rate

3.4 Sampling Technique
    - Sampling method used
    - Randomization procedure (if applicable)
    - Allocation concealment

3.5 Research Instruments
    - Description of assessment tools
    - Validity and reliability of instruments
    - Clinical measures used
    - Outcome measures

3.6 Intervention Protocol (if applicable)
    - Detailed intervention description
    - Duration and frequency
    - Provider qualifications
    - Fidelity measures

3.7 Data Collection Procedure
    - Timeline of data collection
    - Pre-test and post-test procedures
    - Follow-up schedule

3.8 Data Analysis
    - Statistical tests for each objective
    - Software used (SPSS, R, SAS)
    - Handling of missing data

3.9 Ethical Considerations
    - Institutional Review Board approval
    - Informed consent process
    - Risks and benefits
    - Confidentiality measures
    - Voluntary participation

REQUIRED TABLES:
- Table 3.1: Inclusion and Exclusion Criteria
- Table 3.2: Assessment Instruments and Their Psychometric Properties
- Table 3.3: Intervention Protocol (if applicable)

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS (HEALTHCARE/NURSING)

REQUIRED SECTIONS:

4.1 Participant Flow
    - CONSORT-style flow diagram
    - Enrollment numbers
    - Dropouts and reasons
    - Final analysis sample

4.2 Baseline Characteristics
    - Demographic characteristics
    - Clinical characteristics
    - Group comparisons at baseline (if applicable)
    - Homogeneity testing

4.3 Primary Outcome Findings
    - Main outcome measures
    - Pre-intervention vs post-intervention
    - Statistical analysis results
    - Clinical significance interpretation

4.4 Secondary Outcome Findings
    - Additional outcome measures
    - Supporting analyses

4.5 Subgroup Analysis (if applicable)
    - Analysis by demographic groups
    - Analysis by clinical characteristics

4.6 Adverse Events (if applicable)
    - Documentation of any adverse events
    - Severity and management

REQUIRED TABLES:
- Table 4.1: Demographic Characteristics of Participants
- Table 4.2: Clinical Characteristics at Baseline
- Table 4.3: Comparison of Pre and Post Intervention Scores
- Table 4.4: Summary of Hypothesis Testing Results

RESULTS;
    }

    public function getTableRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            3 => [
                [
                    'type' => 'inclusion_exclusion',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Inclusion and exclusion criteria',
                    'columns' => ['Criteria Type', 'Criterion', 'Rationale'],
                ],
                [
                    'type' => 'assessment_instruments',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Assessment instruments used',
                    'columns' => ['Instrument', 'Purpose', 'Domains', 'Scoring', 'Validity/Reliability'],
                ],
            ],
            4 => [
                [
                    'type' => 'demographic_characteristics',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Demographic characteristics of participants',
                    'columns' => ['Characteristic', 'Category', 'Frequency (n)', 'Percentage (%)'],
                ],
                [
                    'type' => 'clinical_outcomes',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Pre and post intervention clinical outcomes',
                    'columns' => ['Outcome Variable', 'Pre Mean (SD)', 'Post Mean (SD)', 't-value', 'p-value', 'Effect Size'],
                ],
                [
                    'type' => 'hypothesis_results',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Summary of hypothesis testing',
                    'columns' => ['Hypothesis', 'Statistical Test', 'Result', 'p-value', 'Decision'],
                ],
            ],
            default => [],
        };
    }

    public function getDiagramRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            3 => [
                [
                    'type' => 'study_flow',
                    'required' => true,
                    'can_generate' => true,
                    'description' => 'CONSORT-style participant flow diagram',
                    'format' => "flowchart TD\n    A[Assessed for Eligibility n=X] --> B[Excluded n=X]\n    A --> C[Enrolled n=X]\n    C --> D[Intervention Group n=X]\n    C --> E[Control Group n=X]\n    D --> F[Follow-up n=X]\n    E --> G[Follow-up n=X]\n    F --> H[Analyzed n=X]\n    G --> I[Analyzed n=X]",
                ],
                [
                    'type' => 'intervention_protocol',
                    'required' => false,
                    'can_generate' => true,
                    'description' => 'Intervention protocol flowchart',
                    'format' => "flowchart TD\n    A[Baseline Assessment] --> B[Randomization]\n    B --> C[Intervention]\n    C --> D[Week 1-4: Phase 1]\n    D --> E[Week 5-8: Phase 2]\n    E --> F[Post-test Assessment]\n    F --> G[Follow-up]",
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'statistics' => [
                ['name' => 'IBM SPSS Statistics', 'url' => 'ibm.com/spss', 'cost' => 'Academic License', 'best_for' => 'Clinical data analysis'],
                ['name' => 'Epi Info', 'url' => 'cdc.gov/epiinfo', 'cost' => 'Free', 'best_for' => 'Epidemiological analysis'],
                ['name' => 'GraphPad Prism', 'url' => 'graphpad.com', 'cost' => 'Academic License', 'best_for' => 'Biomedical statistics'],
            ],
            'data_collection' => [
                ['name' => 'REDCap', 'url' => 'project-redcap.org', 'cost' => 'Free (Institutional)', 'best_for' => 'Clinical data capture'],
                ['name' => 'ODK', 'url' => 'getodk.org', 'cost' => 'Free', 'best_for' => 'Mobile health surveys'],
            ],
        ];
    }
}
