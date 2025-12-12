<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class AgricultureTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


AGRICULTURE-SPECIFIC GUIDELINES:

AGRICULTURAL RESEARCH STANDARDS:
- Use proper agricultural terminology
- Include soil, climate, and environmental data
- Reference agronomic standards and practices
- Consider seasonal and geographical factors

EXPERIMENTAL DESIGN:
- Randomized Complete Block Design (RCBD) common
- Split-plot designs for factorial experiments
- Field layout with replication
- Treatment combinations clearly defined

DATA COLLECTION:
- Growth parameters (height, leaf area, biomass)
- Yield components and total yield
- Soil analysis (pH, nutrients, organic matter)
- Pest and disease incidence

STATISTICAL ANALYSIS:
- Analysis of Variance (ANOVA)
- Mean separation tests (LSD, Duncan, Tukey)
- Correlation and regression for yield prediction
- Growth curve analysis
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (AGRICULTURE)

REQUIRED SECTIONS:

3.1 Study Area/Location
    - Geographic location (coordinates if possible)
    - Climate data (rainfall, temperature)
    - Soil type and characteristics
    - Previous land use

3.2 Experimental Design
    - Design type (RCBD, CRD, Split-plot, etc.)
    - Number of treatments
    - Number of replications
    - Plot size and spacing
    - Field layout diagram

3.3 Planting Materials
    - Crop variety/varieties used
    - Source of seeds/planting materials
    - Seed rate or planting density
    - Seed treatment (if any)

3.4 Treatments
    - Treatment details (fertilizer rates, irrigation levels, etc.)
    - Treatment combinations
    - Control treatment description

3.5 Cultural Practices
    - Land preparation
    - Planting method and date
    - Fertilizer application
    - Irrigation schedule
    - Weed management
    - Pest and disease control

3.6 Data Collection
    - Growth parameters measured
    - Yield and yield components
    - Sampling procedure
    - Data collection schedule

3.7 Laboratory Analysis (if applicable)
    - Soil analysis methods
    - Plant tissue analysis
    - Quality parameters

3.8 Statistical Analysis
    - ANOVA procedure
    - Mean comparison tests
    - Software used (GenStat, SAS, R)

REQUIRED TABLES:
- Table 3.1: Soil Physical and Chemical Properties of Study Site
- Table 3.2: Treatment Combinations
- Table 3.3: Schedule of Farm Operations

REQUIRED DIAGRAMS:
- Field layout diagram showing plot arrangement
- Treatment application diagram

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS (AGRICULTURE)

REQUIRED SECTIONS:

4.1 Weather/Climate Data
    - Rainfall during study period
    - Temperature (min, max, mean)
    - Comparison with long-term averages

4.2 Soil Analysis Results
    - Pre-planting soil properties
    - Post-harvest soil properties (if applicable)

4.3 Growth Parameters
    Present results by parameter:
    - Plant height at different growth stages
    - Number of leaves/tillers
    - Leaf area/leaf area index
    - Days to flowering/maturity

4.4 Yield and Yield Components
    - Number of fruits/pods/panicles
    - Weight per plant
    - Grain/fruit yield per hectare
    - Harvest index

4.5 Quality Parameters (if applicable)
    - Protein content
    - Oil content
    - Other quality indicators

4.6 Effect of Treatments
    For each treatment factor:
    - Main effects
    - Interaction effects (if significant)
    - Mean separation results

4.7 Correlation Analysis
    - Correlation between growth and yield parameters
    - Key relationships identified

4.8 Summary of Findings
    - Best performing treatments
    - Key findings by objective

REQUIRED TABLES:
- Table 4.1: Effect of Treatments on Growth Parameters
- Table 4.2: Effect of Treatments on Yield and Yield Components
- Table 4.3: ANOVA Summary for Key Variables
- Table 4.4: Correlation Matrix of Growth and Yield Parameters

TABLE FORMAT:
Include LSD or SE values for mean comparisons
Use letter notation for mean separation (a, b, c)

RESULTS;
    }

    public function getTableRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            3 => [
                [
                    'type' => 'soil_properties',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Soil physical and chemical properties',
                    'columns' => ['Property', 'Value', 'Unit', 'Rating'],
                ],
                [
                    'type' => 'treatment_combinations',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Treatment combinations',
                    'columns' => ['Treatment', 'Factor 1', 'Factor 2', 'Description'],
                ],
            ],
            4 => [
                [
                    'type' => 'growth_parameters',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Effect of treatments on growth parameters',
                    'columns' => ['Treatment', 'Plant Height (cm)', 'No. of Leaves', 'Leaf Area (cm²)', 'Days to Maturity'],
                ],
                [
                    'type' => 'yield_components',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Effect of treatments on yield and yield components',
                    'columns' => ['Treatment', 'Pods/Plant', 'Seeds/Pod', '100-Seed Weight (g)', 'Yield (kg/ha)'],
                ],
                [
                    'type' => 'anova_summary',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'ANOVA summary for key variables',
                    'columns' => ['Source', 'df', 'Mean Square', 'F-value', 'p-value', 'Significance'],
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
                    'type' => 'field_layout',
                    'required' => true,
                    'can_generate' => false,
                    'description' => 'Field layout showing plot arrangement',
                    'tool' => 'Microsoft Word/Excel or Draw.io',
                ],
                [
                    'type' => 'experimental_design',
                    'required' => false,
                    'can_generate' => true,
                    'description' => 'Experimental design diagram',
                    'format' => "graph TD\n    subgraph Block I\n        T1a[T1]\n        T2a[T2]\n        T3a[T3]\n    end\n    subgraph Block II\n        T2b[T2]\n        T3b[T3]\n        T1b[T1]\n    end\n    subgraph Block III\n        T3c[T3]\n        T1c[T1]\n        T2c[T2]\n    end",
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
                    'Yield calculation: (Plot yield × 10000) / Plot area',
                    'Harvest Index: (Economic yield / Biological yield) × 100',
                    'Percent increase: ((Treatment - Control) / Control) × 100',
                    'LSD calculation at 5% level',
                ],
                'examples' => [
                    'Calculate grain yield per hectare from plot data',
                    'Calculate percentage increase over control',
                    'Interpret mean separation letters',
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'statistics' => [
                ['name' => 'GenStat', 'url' => 'vsni.co.uk/genstat', 'cost' => 'Academic License', 'best_for' => 'Agricultural experiments'],
                ['name' => 'SAS', 'url' => 'sas.com', 'cost' => 'Academic License', 'best_for' => 'Advanced statistics'],
                ['name' => 'R/agricolae', 'url' => 'cran.r-project.org', 'cost' => 'Free', 'best_for' => 'Agricultural analysis'],
            ],
            'data_collection' => [
                ['name' => 'ODK', 'url' => 'getodk.org', 'cost' => 'Free', 'best_for' => 'Field data collection'],
                ['name' => 'Excel', 'url' => 'microsoft.com', 'cost' => 'Paid', 'best_for' => 'Data organization'],
            ],
        ];
    }
}
