<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class ScienceTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


SCIENCE-SPECIFIC GUIDELINES:

EXPERIMENTAL STANDARDS:
- Document all experimental procedures with reproducible detail
- Include equipment specifications and calibration data
- Report measurements with appropriate significant figures
- Use SI units consistently throughout

DATA ANALYSIS:
- Apply appropriate statistical tests for experimental data
- Report mean, standard deviation, and confidence intervals
- Include error analysis and propagation
- Use scientific notation where appropriate

CHEMICAL/BIOLOGICAL NOTATION:
- Use proper chemical formulas and equations
- Include reaction conditions and yields
- Document safety precautions
- Reference standard protocols (if applicable)

SCIENTIFIC WRITING:
- Present methods in sufficient detail for replication
- Distinguish between observations and interpretations
- Connect findings to existing scientific literature
- Discuss limitations and sources of error
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (SCIENCE)

REQUIRED SECTIONS:

3.1 Research Design
    - Experimental design type
    - Variables (independent, dependent, controlled)
    - Treatment conditions

3.2 Materials and Equipment
    - Complete list of materials with specifications
    - Equipment with model numbers and manufacturers
    - Reagent grades and purity
    - Source/supplier information

3.3 Sample Preparation (if applicable)
    - Sample collection procedure
    - Preservation methods
    - Preparation protocols

3.4 Experimental Procedure
    - Step-by-step protocol
    - Conditions (temperature, time, concentrations)
    - Number of replicates
    - Controls used

3.5 Measurements and Instrumentation
    - Measurement techniques
    - Calibration procedures
    - Detection limits and accuracy

3.6 Data Collection
    - Data recording methods
    - Number of trials/replicates
    - Quality control measures

3.7 Statistical Analysis
    - Statistical tests used
    - Software for analysis
    - Significance level

3.8 Safety Considerations
    - Hazard identification
    - Safety measures implemented
    - Waste disposal procedures

REQUIRED TABLES:
- Table 3.1: Materials and Equipment List
- Table 3.2: Experimental Conditions
- Table 3.3: Variables and Measurements

CRITICAL: END CHAPTER WITH REFERENCES SECTION
- After all content sections, include a "References" section
- List ALL sources cited in this chapter in APA 7th edition format
- Sort alphabetically by author's last name

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS (SCIENCE)

REQUIRED SECTIONS:

4.1 Experimental Observations
    - Physical observations during experiments
    - Qualitative observations
    - Any unexpected occurrences

4.2 Data Presentation
    - Tabulated experimental data
    - Measurements with uncertainties
    - Graphical representation of trends

4.3 Statistical Analysis
    - Descriptive statistics for each treatment
    - Comparison between groups
    - Significance testing results

4.4 Results by Objective
    4.4.1 Objective 1
        - Data and analysis
        - Interpretation

    4.4.2 Objective 2
        - Data and analysis
        - Interpretation

4.5 Comparison with Theoretical Values
    - Comparison with expected/theoretical values
    - Percentage error calculations
    - Discussion of discrepancies

4.6 Summary of Key Findings
    - Main experimental findings
    - Relationship to hypotheses

REQUIRED TABLES:
- Table 4.1: Raw Experimental Data
- Table 4.2: Descriptive Statistics by Treatment/Group
- Table 4.3: Statistical Test Results
- Table 4.4: Comparison with Theoretical/Literature Values

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
                    'type' => 'materials_equipment',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'List of materials and equipment',
                    'columns' => ['Item', 'Specification/Grade', 'Source/Manufacturer', 'Quantity'],
                ],
                [
                    'type' => 'experimental_design',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Experimental design and conditions',
                    'columns' => ['Treatment/Group', 'Conditions', 'Variables Controlled', 'Replicates'],
                ],
            ],
            4 => [
                [
                    'type' => 'experimental_data',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Experimental results with measurements',
                    'columns' => ['Sample/Trial', 'Measurement 1', 'Measurement 2', 'Mean', 'Std. Dev'],
                ],
                [
                    'type' => 'statistical_results',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Statistical analysis results',
                    'columns' => ['Comparison', 'Test Used', 'Statistic', 'df', 'p-value', 'Significance'],
                ],
                [
                    'type' => 'comparison_theoretical',
                    'required' => false,
                    'mock_data' => true,
                    'description' => 'Comparison with theoretical values',
                    'columns' => ['Parameter', 'Theoretical Value', 'Experimental Value', 'Error (%)'],
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
                    'type' => 'experimental_setup',
                    'required' => false,
                    'can_generate' => false,
                    'description' => 'Diagram of experimental setup',
                    'tool' => 'Draw.io, ChemDraw, or laboratory diagram software',
                ],
                [
                    'type' => 'procedure_flowchart',
                    'required' => true,
                    'can_generate' => true,
                    'description' => 'Experimental procedure flowchart',
                    'format' => "flowchart TD\n    A[Sample Preparation] --> B[Setup Equipment]\n    B --> C[Calibration]\n    C --> D[Run Experiment]\n    D --> E[Record Data]\n    E --> F{More Replicates?}\n    F -->|Yes| D\n    F -->|No| G[Data Analysis]",
                ],
            ],
            4 => [
                [
                    'type' => 'results_graph',
                    'required' => false,
                    'can_generate' => false,
                    'description' => 'Graph of experimental results',
                    'tool' => 'Excel, Origin, GraphPad Prism, or Python matplotlib',
                ],
            ],
            default => [],
        };
    }

    public function getCalculationRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            4 => [
                'required' => true,
                'types' => [
                    'Mean calculation',
                    'Standard deviation',
                    'Percentage error: ((Experimental - Theoretical) / Theoretical) × 100',
                    'Coefficient of variation: (SD / Mean) × 100',
                ],
                'examples' => [
                    'Calculate mean and standard deviation of measurements',
                    'Calculate percentage error from theoretical value',
                    'Calculate reproducibility coefficient',
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'analysis' => [
                ['name' => 'Origin Pro', 'url' => 'originlab.com', 'cost' => 'Academic License', 'best_for' => 'Scientific graphing'],
                ['name' => 'GraphPad Prism', 'url' => 'graphpad.com', 'cost' => 'Academic License', 'best_for' => 'Statistical analysis'],
                ['name' => 'R/RStudio', 'url' => 'rstudio.com', 'cost' => 'Free', 'best_for' => 'Advanced statistics'],
            ],
            'molecular' => [
                ['name' => 'ChemDraw', 'url' => 'perkinelmer.com', 'cost' => 'Academic License', 'best_for' => 'Chemical structures'],
                ['name' => 'Avogadro', 'url' => 'avogadro.cc', 'cost' => 'Free', 'best_for' => '3D molecular modeling'],
            ],
        ];
    }
}
