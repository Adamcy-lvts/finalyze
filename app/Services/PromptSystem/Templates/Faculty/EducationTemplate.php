<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class EducationTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


EDUCATION-SPECIFIC GUIDELINES:

PEDAGOGICAL FOCUS:
- Apply educational theories appropriately
- Reference curriculum standards where relevant
- Consider diverse learning needs and styles
- Discuss implications for teaching practice

RESEARCH APPROACHES:
- Action research methodology
- Classroom-based research design
- Mixed methods commonly used
- Educational assessment and evaluation

DATA SOURCES:
- Student performance data
- Teacher observations
- Surveys and questionnaires
- Interviews and focus groups
- Document analysis (lesson plans, curricula)

EDUCATIONAL CONTEXT:
- School/institution setting description
- Grade level and subject matter
- Student population characteristics
- Educational policy context
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (EDUCATION)

REQUIRED SECTIONS:

3.1 Research Design
    - Research approach (qualitative, quantitative, mixed methods)
    - Research type (descriptive, experimental, action research)
    - Justification for design choice

3.2 Research Setting
    - School/institution description
    - Location and type
    - Relevant contextual factors

3.3 Population and Sample
    - Target population (students, teachers, administrators)
    - Sampling technique
    - Sample size with justification
    - Inclusion/exclusion criteria

3.4 Research Instruments
    - Achievement tests
    - Questionnaires
    - Interview guides
    - Observation checklists
    - Validity and reliability of instruments

3.5 Intervention/Treatment (if applicable)
    - Description of educational intervention
    - Lesson plans or curriculum materials
    - Duration and implementation
    - Fidelity measures

3.6 Data Collection Procedure
    - Pre-test and post-test administration
    - Data collection timeline
    - Ethical procedures with minors

3.7 Data Analysis
    - Statistical techniques for quantitative data
    - Thematic analysis for qualitative data
    - Software used

3.8 Ethical Considerations
    - Institutional approval
    - Parental consent
    - Student assent
    - Confidentiality measures

REQUIRED TABLES:
- Table 3.1: Distribution of Participants
- Table 3.2: Research Instruments and Their Properties
- Table 3.3: Data Analysis Plan

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS (EDUCATION)

REQUIRED SECTIONS:

4.1 Demographic Profile of Respondents
    - Characteristics of participants
    - Distribution by relevant categories
    - Summary of sample

4.2 Achievement/Performance Data
    - Pre-test and post-test scores (if applicable)
    - Performance levels
    - Comparison of groups

4.3 Findings by Research Question
    For each research question/objective:
    - Data presentation
    - Statistical analysis results
    - Interpretation

4.4 Comparison of Groups (if applicable)
    - Experimental vs control group
    - Pre vs post intervention
    - Effect size calculations

4.5 Qualitative Findings (if applicable)
    - Themes from interviews/observations
    - Supporting quotes
    - Integration with quantitative findings

4.6 Summary of Findings
    - Key findings by objective
    - Implications for education

REQUIRED TABLES:
- Table 4.1: Demographic Characteristics of Respondents
- Table 4.2: Pre-test and Post-test Performance Summary
- Table 4.3: Comparison of Experimental and Control Groups
- Table 4.4: Summary of Hypothesis Testing

RESULTS;
    }

    public function getTableRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            3 => [
                [
                    'type' => 'participant_distribution',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Distribution of participants',
                    'columns' => ['Category', 'Group', 'Number', 'Percentage (%)'],
                ],
                [
                    'type' => 'instruments',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Research instruments and properties',
                    'columns' => ['Instrument', 'Purpose', 'No. of Items', 'Validity', 'Reliability'],
                ],
            ],
            4 => [
                [
                    'type' => 'demographic_characteristics',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Demographic characteristics',
                    'columns' => ['Characteristic', 'Category', 'Frequency', 'Percentage (%)'],
                ],
                [
                    'type' => 'pretest_posttest',
                    'required' => false,
                    'mock_data' => true,
                    'description' => 'Pre-test and post-test performance',
                    'columns' => ['Group', 'N', 'Pre-test Mean (SD)', 'Post-test Mean (SD)', 'Gain Score'],
                ],
                [
                    'type' => 'group_comparison',
                    'required' => false,
                    'mock_data' => true,
                    'description' => 'Comparison of experimental and control groups',
                    'columns' => ['Variable', 'Experimental Mean (SD)', 'Control Mean (SD)', 't-value', 'p-value', 'Effect Size'],
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
                    'type' => 'research_design',
                    'required' => false,
                    'can_generate' => true,
                    'description' => 'Research design diagram',
                    'format' => "flowchart TD\n    A[Sample Selection] --> B[Pre-test]\n    B --> C{Random Assignment}\n    C --> D[Experimental Group]\n    C --> E[Control Group]\n    D --> F[Treatment]\n    E --> G[Regular Instruction]\n    F --> H[Post-test]\n    G --> H\n    H --> I[Data Analysis]",
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'statistics' => [
                ['name' => 'IBM SPSS Statistics', 'url' => 'ibm.com/spss', 'cost' => 'Academic License', 'best_for' => 'Educational research'],
                ['name' => 'R/RStudio', 'url' => 'rstudio.com', 'cost' => 'Free', 'best_for' => 'Advanced analysis'],
            ],
            'assessment' => [
                ['name' => 'Google Forms', 'url' => 'forms.google.com', 'cost' => 'Free', 'best_for' => 'Online surveys'],
                ['name' => 'Kahoot', 'url' => 'kahoot.com', 'cost' => 'Free tier', 'best_for' => 'Interactive assessments'],
            ],
        ];
    }
}
