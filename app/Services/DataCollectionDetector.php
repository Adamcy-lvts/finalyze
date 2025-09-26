<?php

namespace App\Services;

use App\Models\Chapter;

class DataCollectionDetector
{
    /**
     * Detection keywords for different types of data collection
     */
    private array $detectionKeywords = [
        'survey' => [
            'survey', 'questionnaire', 'questionnaires', 'respondents', 'participants',
            'interview', 'interviews', 'focus group', 'focus groups', 'survey data',
            'survey results', 'questionnaire design', 'data collection', 'primary data',
            'field study', 'field research', 'empirical study', 'sample size',
            'demographics', 'survey methodology', 'response rate', 'likert scale',
        ],
        'experiment' => [
            'experiment', 'experiments', 'laboratory', 'lab test', 'lab tests',
            'experimental design', 'control group', 'test group', 'variables',
            'hypothesis', 'methodology', 'procedure', 'experimental setup',
            'data analysis', 'results', 'findings', 'experimental data',
            'measurements', 'observations', 'trial', 'trials', 'protocol',
        ],
        'engineering' => [
            'prototype', 'prototypes', 'circuit', 'circuits', 'design', 'simulation',
            'CAD', 'modeling', 'schematic', 'schematics', 'specifications',
            'testing', 'validation', 'verification', 'engineering design',
            'technical specifications', 'blueprint', 'blueprints', 'fabrication',
            'manufacturing', 'assembly', 'component', 'components', 'system design',
        ],
        'statistical' => [
            'statistical analysis', 'statistics', 'data analysis', 'regression',
            'correlation', 'significance', 'p-value', 'confidence interval',
            'standard deviation', 'mean', 'median', 'variance', 'ANOVA',
            'chi-square', 'statistical test', 'statistical tests', 'sample',
            'population', 'dataset', 'datasets', 'statistical software',
            'SPSS', 'R programming', 'statistical modeling', 'hypothesis testing',
        ],
        'construction' => [
            'construction', 'building', 'structural', 'foundation', 'concrete',
            'steel', 'materials', 'site work', 'civil engineering', 'architecture',
            'project management', 'construction project', 'site analysis',
            'soil testing', 'structural analysis', 'building codes', 'permits',
            'contractor', 'subcontractor', 'construction schedule', 'cost estimation',
        ],
    ];

    /**
     * Template definitions for different data collection types
     */
    private array $templates = [
        'survey' => [
            'title' => 'Survey Data Collection Template',
            'content' => '
## Survey Methodology

### Research Objectives
- [Insert specific research questions]
- [Define what you aim to discover]

### Target Population
- **Population**: [Describe your target population]
- **Sample Size**: [Calculate required sample size]
- **Sampling Method**: [Random, stratified, convenience, etc.]

### Survey Design
#### Demographics Section
- Age range
- Gender
- Education level
- Occupation
- [Add relevant demographic questions]

#### Main Survey Questions
1. [Likert scale question example]
   - Strongly Disagree (1)
   - Disagree (2)
   - Neutral (3)
   - Agree (4)
   - Strongly Agree (5)

2. [Multiple choice question]
3. [Open-ended question]

### Data Collection Plan
- **Method**: Online/Paper/Phone/In-person
- **Duration**: [Timeline for data collection]
- **Distribution**: [How will you reach participants]

### Expected Results
[Describe what types of insights you expect to gain]

### Ethical Considerations
- Informed consent procedures
- Privacy protection measures
- Data storage and handling protocols
',
        ],
        'experiment' => [
            'title' => 'Laboratory Experiment Template',
            'content' => '
## Experimental Design

### Research Hypothesis
- **Null Hypothesis (H0)**: [State null hypothesis]
- **Alternative Hypothesis (H1)**: [State alternative hypothesis]

### Variables
- **Independent Variable(s)**: [What you will manipulate]
- **Dependent Variable(s)**: [What you will measure]
- **Control Variables**: [What you will keep constant]

### Experimental Setup
#### Materials and Equipment
- [List all materials needed]
- [List all equipment and instruments]
- [Specify quantities and specifications]

#### Procedure
1. [Step-by-step experimental procedure]
2. [Include safety protocols]
3. [Specify measurement intervals]
4. [Detail recording methods]

### Sample Groups
- **Control Group**: [Description and size]
- **Experimental Group(s)**: [Description and size]
- **Sample Size Justification**: [Why this sample size]

### Data Collection Protocol
- **Measurement Methods**: [How will you collect data]
- **Recording Format**: [Data recording sheets/digital]
- **Quality Control**: [How will you ensure accuracy]

### Expected Outcomes
[Describe anticipated results and their significance]

### Statistical Analysis Plan
- [Which statistical tests will you use]
- [Significance level (α = 0.05)]
- [Software for analysis]
',
        ],
        'engineering' => [
            'title' => 'Engineering Design & Prototype Template',
            'content' => '
## Engineering Design Specifications

### Project Requirements
#### Functional Requirements
- [Primary functions the design must perform]
- [Performance specifications]
- [Operational parameters]

#### Non-Functional Requirements
- [Safety requirements]
- [Environmental conditions]
- [Cost constraints]
- [Timeline constraints]

### Design Process
#### Initial Concept
- [Describe the basic design concept]
- [Include preliminary sketches or diagrams]

#### Technical Specifications
- **Materials**: [Specify materials and properties]
- **Dimensions**: [Key measurements and tolerances]
- **Components**: [List major components]
- **Interfaces**: [How components connect]

### Prototype Development
#### Prototype Objectives
- [What will the prototype demonstrate]
- [Which aspects will be tested]

#### Manufacturing/Assembly Plan
1. [Step-by-step assembly process]
2. [Required tools and equipment]
3. [Quality check points]

### Testing and Validation
#### Test Plan
- **Performance Tests**: [What will you measure]
- **Safety Tests**: [Safety validation procedures]
- **Durability Tests**: [Long-term performance]

#### Success Criteria
- [Define what constitutes successful performance]
- [Quantifiable metrics]

### Documentation
- Technical drawings (CAD files)
- Bill of materials (BOM)
- Assembly instructions
- Test results and analysis
',
        ],
        'statistical' => [
            'title' => 'Statistical Analysis Template',
            'content' => '
## Statistical Analysis Plan

### Research Questions
1. [Primary research question]
2. [Secondary research questions]

### Data Description
#### Dataset Information
- **Source**: [Where will data come from]
- **Size**: [Expected number of observations]
- **Variables**: [List and describe key variables]

#### Variable Types
- **Continuous Variables**: [List numerical variables]
- **Categorical Variables**: [List categorical variables]
- **Ordinal Variables**: [List ordered categorical variables]

### Descriptive Statistics
#### Summary Statistics
- Mean, median, mode for continuous variables
- Frequency distributions for categorical variables
- Standard deviations and ranges
- Identification of outliers

#### Data Visualization
- Histograms for continuous variables
- Bar charts for categorical variables
- Scatter plots for relationships
- Box plots for group comparisons

### Inferential Statistics
#### Hypothesis Testing
- **Primary Hypothesis**: [State clearly]
- **Statistical Test**: [t-test, ANOVA, chi-square, etc.]
- **Significance Level**: α = 0.05
- **Power Analysis**: [Sample size justification]

#### Assumptions
- [List assumptions for chosen statistical tests]
- [How will you test these assumptions]
- [Alternative approaches if assumptions are violated]

### Software and Tools
- **Statistical Software**: [R, SPSS, Python, etc.]
- **Packages/Libraries**: [Specific tools you will use]
- **Data Management**: [How data will be organized]

### Expected Results
[Describe anticipated findings and their implications]

### Reporting Plan
- Tables and figures to be included
- Effect size reporting
- Confidence intervals
- Practical significance interpretation
',
        ],
        'construction' => [
            'title' => 'Construction Project Template',
            'content' => '
## Construction Project Plan

### Project Overview
#### Project Description
- [Detailed description of construction project]
- [Project scope and objectives]
- [Location and site characteristics]

#### Stakeholders
- **Client**: [Project owner information]
- **Contractor**: [Primary contractor details]
- **Subcontractors**: [Specialized contractors]
- **Regulatory Bodies**: [Relevant authorities]

### Site Analysis
#### Site Conditions
- **Location**: [Address and coordinates]
- **Site Dimensions**: [Area and boundaries]
- **Topography**: [Elevation, slopes, drainage]
- **Soil Conditions**: [Soil type and bearing capacity]

#### Environmental Factors
- Climate considerations
- Environmental impact assessment
- Sustainability requirements

### Design and Specifications
#### Structural Design
- **Foundation Type**: [Shallow, deep, slab, etc.]
- **Structural System**: [Frame, load-bearing walls, etc.]
- **Materials**: [Concrete, steel, wood specifications]

#### Building Systems
- Electrical systems
- Plumbing and HVAC
- Fire safety systems
- Accessibility features

### Project Schedule
#### Major Phases
1. **Pre-construction** ([Duration])
   - Permits and approvals
   - Site preparation
   - Material procurement

2. **Construction** ([Duration])
   - Foundation work
   - Structural work
   - Systems installation
   - Finishing work

3. **Post-construction** ([Duration])
   - Final inspections
   - Commissioning
   - Handover

### Quality Control
#### Inspection Points
- [Critical inspection stages]
- [Quality standards and specifications]
- [Testing requirements]

#### Documentation
- Progress photos
- Inspection reports
- Material certifications
- Change orders
',
        ],
    ];

    /**
     * Detect data collection needs in chapter content
     */
    public function detectDataCollectionNeeds(Chapter $chapter): array
    {
        $content = strtolower($chapter->content ?? '');
        $detectedTypes = [];
        $confidence = [];

        foreach ($this->detectionKeywords as $type => $keywords) {
            $matches = 0;
            $totalKeywords = count($keywords);

            foreach ($keywords as $keyword) {
                if (str_contains($content, $keyword)) {
                    $matches++;
                }
            }

            if ($matches > 0) {
                $confidenceScore = ($matches / $totalKeywords) * 100;
                $detectedTypes[] = $type;
                $confidence[$type] = round($confidenceScore, 1);
            }
        }

        return [
            'types' => $detectedTypes,
            'confidence' => $confidence,
            'hasDataCollectionNeeds' => ! empty($detectedTypes),
        ];
    }

    /**
     * Get appropriate template for detected data collection type
     */
    public function getTemplate(string $type): ?array
    {
        return $this->templates[$type] ?? null;
    }

    /**
     * Get all available templates
     */
    public function getAllTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Generate placeholder content based on detected needs
     */
    public function generatePlaceholder(Chapter $chapter): array
    {
        $detection = $this->detectDataCollectionNeeds($chapter);

        if (! $detection['hasDataCollectionNeeds']) {
            return [
                'hasPlaceholder' => false,
                'message' => 'No data collection requirements detected.',
            ];
        }

        // Get the most confident detection
        $topType = array_key_first($detection['confidence']);
        $template = $this->getTemplate($topType);

        return [
            'hasPlaceholder' => true,
            'detectedType' => $topType,
            'confidence' => $detection['confidence'][$topType],
            'template' => $template,
            'allDetected' => $detection['types'],
            'allConfidence' => $detection['confidence'],
        ];
    }

    /**
     * Suggest improvements for data collection sections
     */
    public function suggestImprovements(Chapter $chapter): array
    {
        $detection = $this->detectDataCollectionNeeds($chapter);
        $suggestions = [];

        if (! $detection['hasDataCollectionNeeds']) {
            return $suggestions;
        }

        foreach ($detection['types'] as $type) {
            switch ($type) {
                case 'survey':
                    $suggestions[] = [
                        'type' => 'survey',
                        'title' => 'Survey Design Recommendations',
                        'suggestions' => [
                            'Define clear research objectives before designing questions',
                            'Calculate appropriate sample size for statistical significance',
                            'Include demographic questions for better analysis',
                            'Use validated scales when possible (Likert, semantic differential)',
                            'Pre-test your survey with a small group',
                            'Consider response bias and non-response issues',
                        ],
                    ];
                    break;

                case 'experiment':
                    $suggestions[] = [
                        'type' => 'experiment',
                        'title' => 'Experimental Design Tips',
                        'suggestions' => [
                            'Clearly state null and alternative hypotheses',
                            'Control for confounding variables',
                            'Randomize participant assignment to groups',
                            'Use appropriate sample sizes (power analysis)',
                            'Plan for data quality checks and outlier detection',
                            'Document procedures for reproducibility',
                        ],
                    ];
                    break;

                case 'engineering':
                    $suggestions[] = [
                        'type' => 'engineering',
                        'title' => 'Engineering Design Guidelines',
                        'suggestions' => [
                            'Document all design requirements and constraints',
                            'Create detailed technical drawings and specifications',
                            'Plan for prototype testing and validation',
                            'Consider safety factors and failure modes',
                            'Document material properties and selection criteria',
                            'Include cost analysis and manufacturing considerations',
                        ],
                    ];
                    break;

                case 'statistical':
                    $suggestions[] = [
                        'type' => 'statistical',
                        'title' => 'Statistical Analysis Best Practices',
                        'suggestions' => [
                            'Check data quality and missing values before analysis',
                            'Verify statistical test assumptions',
                            'Report effect sizes along with p-values',
                            'Use appropriate multiple comparison corrections',
                            'Include confidence intervals in results',
                            'Consider practical significance, not just statistical significance',
                        ],
                    ];
                    break;

                case 'construction':
                    $suggestions[] = [
                        'type' => 'construction',
                        'title' => 'Construction Project Planning',
                        'suggestions' => [
                            'Conduct thorough site analysis before design',
                            'Obtain all necessary permits and approvals',
                            'Plan for weather and seasonal considerations',
                            'Include quality control checkpoints',
                            'Document all changes and variations',
                            'Plan for safety protocols and risk management',
                        ],
                    ];
                    break;
            }
        }

        return $suggestions;
    }
}
