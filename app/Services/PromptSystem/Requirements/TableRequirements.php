<?php

namespace App\Services\PromptSystem\Requirements;

class TableRequirements
{
    /**
     * Get table requirements based on chapter type, project type, and faculty
     */
    public function getRequirements(string $chapterType, string $projectType, string $faculty): array
    {
        $baseRequirements = $this->getBaseRequirements($chapterType);

        // Get faculty-specific requirements first
        $facultyRequirements = $this->getFacultyRequirements($chapterType, $faculty);

        // Get project-type specific requirements
        $projectRequirements = $this->getProjectTypeRequirements($chapterType, $projectType);

        // Merge with faculty taking precedence for conflicts
        return array_merge($baseRequirements, $projectRequirements, $facultyRequirements);
    }

    /**
     * Base (cross-faculty) table requirements.
     */
    private function getBaseRequirements(string $chapterType): array
    {
        return match ($chapterType) {
            'literature_review' => [
                [
                    'type' => 'synthesis_matrix',
                    'required' => true,
                    'min' => 1,
                    'mock_data' => false,
                    'description' => 'Synthesis matrix summarizing the most relevant studies (populate ONLY from verified sources provided in the prompt)',
                    'columns' => ['Author (Allowed in-text citation)', 'Country/Context', 'Method/Design', 'Key Findings', 'Limitations', 'Relevance to the study context'],
                ],
            ],
            default => [],
        };
    }

    /**
     * Get faculty-specific table requirements
     */
    private function getFacultyRequirements(string $chapterType, string $faculty): array
    {
        $requirements = [
            'engineering' => [
                'methodology' => [
                    [
                        'type' => 'component_specification',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => false, // User must provide actual components
                        'description' => 'Bill of Materials - Components with specifications, quantity, and cost',
                        'columns' => ['Component', 'Model/Part No.', 'Specifications', 'Quantity', 'Unit Cost', 'Total Cost'],
                        'instructions' => [
                            'List all components from your circuit/system design',
                            'Get specifications from component datasheets',
                            'Check current prices from local electronics suppliers',
                            'Include shipping costs if applicable',
                        ],
                    ],
                    [
                        'type' => 'pin_connections',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'Pin connection mapping between components',
                        'columns' => ['Component 1', 'Pin', 'Connection', 'Component 2', 'Pin', 'Description'],
                    ],
                    [
                        'type' => 'power_analysis',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Power consumption analysis for each component',
                        'columns' => ['Component', 'Operating Voltage (V)', 'Current Draw (mA)', 'Power (mW)', 'Operating Mode'],
                    ],
                ],
                'results' => [
                    [
                        'type' => 'test_results',
                        'required' => true,
                        'min' => 2,
                        'mock_data' => true,
                        'description' => 'System performance test results',
                        'columns' => ['Test Parameter', 'Expected Value', 'Measured Value', 'Deviation (%)', 'Status'],
                        'instructions' => [
                            'Define test parameters based on system requirements',
                            'Set up test equipment (multimeter, oscilloscope, etc.)',
                            'Run each test at least 3 times for consistency',
                            'Calculate average values and standard deviation',
                            'Document environmental conditions during testing',
                        ],
                    ],
                    [
                        'type' => 'performance_metrics',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'System efficiency and performance metrics',
                        'columns' => ['Metric', 'Target', 'Achieved', 'Unit', 'Remarks'],
                    ],
                    [
                        'type' => 'comparison',
                        'required' => false,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Comparison with existing solutions',
                        'columns' => ['Feature', 'This System', 'Existing Solution 1', 'Existing Solution 2'],
                    ],
                ],
            ],
            'social_science' => [
                'methodology' => [
                    [
                        'type' => 'variable_operationalization',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'Variables, operational definitions, and measurement scales',
                        'columns' => ['Variable', 'Operational Definition', 'Indicator', 'Scale', 'Source'],
                    ],
                    [
                        'type' => 'reliability_test',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Instrument reliability test (Cronbach\'s Alpha)',
                        'columns' => ['Scale/Section', 'No. of Items', 'Cronbach\'s Alpha', 'Interpretation'],
                        'instructions' => [
                            'Run reliability analysis in SPSS (Analyze > Scale > Reliability Analysis)',
                            'Select items for each scale/section',
                            'Record Cronbach\'s Alpha for each',
                            'Alpha > 0.7 is acceptable, > 0.8 is good',
                        ],
                    ],
                ],
                'results' => [
                    [
                        'type' => 'sample_demographics',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Demographic characteristics of respondents',
                        'columns' => ['Variable', 'Category', 'Frequency', 'Percentage (%)'],
                        'instructions' => [
                            'Collect demographic data from questionnaire Section A',
                            'Enter data into SPSS or Excel',
                            'Run frequency analysis (Analyze > Descriptive > Frequencies)',
                            'Calculate percentages: (frequency/total) × 100',
                        ],
                    ],
                    [
                        'type' => 'descriptive_statistics',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Descriptive statistics for study variables',
                        'columns' => ['Variable', 'N', 'Mean', 'Std. Dev', 'Min', 'Max'],
                    ],
                    [
                        'type' => 'hypothesis_test',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Hypothesis testing results',
                        'columns' => ['Hypothesis', 'Test Used', 'Test Statistic', 'df', 'p-value', 'Decision'],
                        'instructions' => [
                            'State each hypothesis clearly',
                            'Choose appropriate test (t-test, ANOVA, chi-square, correlation)',
                            'Run analysis in SPSS',
                            'Record all values from output',
                            'Compare p-value to significance level (usually 0.05)',
                        ],
                    ],
                    [
                        'type' => 'correlation_regression',
                        'required' => false,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Correlation or regression analysis results',
                        'columns' => ['Variables', 'r/β', 't-value', 'p-value', 'R²', 'Interpretation'],
                    ],
                ],
            ],
            'healthcare' => [
                'methodology' => [
                    [
                        'type' => 'patient_demographics',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Patient/participant demographic characteristics',
                        'columns' => ['Characteristic', 'Category', 'Frequency (n)', 'Percentage (%)'],
                    ],
                    [
                        'type' => 'assessment_instrument',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'Assessment tools/instruments used in the study',
                        'columns' => ['Instrument', 'Purpose', 'Domains/Sections', 'Scoring', 'Validity/Reliability'],
                    ],
                    [
                        'type' => 'intervention_protocol',
                        'required' => false,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'Intervention steps and timeline',
                        'columns' => ['Session/Week', 'Activity', 'Duration', 'Materials', 'Expected Outcome'],
                    ],
                ],
                'results' => [
                    [
                        'type' => 'clinical_outcomes',
                        'required' => true,
                        'min' => 2,
                        'mock_data' => true,
                        'description' => 'Pre and post intervention clinical outcomes',
                        'columns' => ['Outcome Variable', 'Pre-intervention Mean (SD)', 'Post-intervention Mean (SD)', 't-value', 'p-value'],
                        'instructions' => [
                            'Collect baseline data before intervention',
                            'Collect follow-up data after intervention',
                            'Run paired samples t-test in SPSS',
                            'Report means, standard deviations, and p-values',
                        ],
                    ],
                    [
                        'type' => 'statistical_analysis',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Statistical analysis of health outcomes',
                        'columns' => ['Variable', 'Test Used', 'Test Statistic', 'p-value', 'Effect Size', 'Interpretation'],
                    ],
                ],
            ],
            'business' => [
                'methodology' => [
                    [
                        'type' => 'research_variables',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'Study variables and their measurements',
                        'columns' => ['Variable Type', 'Variable Name', 'Measurement', 'Source'],
                    ],
                ],
                'results' => [
                    [
                        'type' => 'financial_analysis',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Financial performance metrics',
                        'columns' => ['Metric', 'Year 1', 'Year 2', 'Year 3', 'Growth Rate (%)'],
                    ],
                    [
                        'type' => 'market_analysis',
                        'required' => false,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Market analysis data',
                        'columns' => ['Segment', 'Market Size', 'Market Share', 'Growth Potential'],
                    ],
                    [
                        'type' => 'survey_results',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Survey response analysis',
                        'columns' => ['Question/Variable', 'Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree', 'Mean'],
                    ],
                ],
            ],
            'science' => [
                'methodology' => [
                    [
                        'type' => 'materials_equipment',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'List of materials and equipment used',
                        'columns' => ['Item', 'Specification', 'Source/Manufacturer', 'Quantity'],
                    ],
                    [
                        'type' => 'experimental_design',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'Experimental design and conditions',
                        'columns' => ['Treatment/Group', 'Conditions', 'Variables Controlled', 'Sample Size'],
                    ],
                ],
                'results' => [
                    [
                        'type' => 'experimental_data',
                        'required' => true,
                        'min' => 2,
                        'mock_data' => true,
                        'description' => 'Experimental results with measurements',
                        'columns' => ['Sample/Trial', 'Measurement 1', 'Measurement 2', 'Mean', 'Std. Dev'],
                    ],
                    [
                        'type' => 'statistical_results',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Statistical analysis of experimental data',
                        'columns' => ['Comparison', 'Test', 'Statistic', 'df', 'p-value', 'Significance'],
                    ],
                ],
            ],
        ];

        return $requirements[$faculty][$chapterType] ?? [];
    }

    /**
     * Get project-type specific requirements (e.g., software, hardware)
     */
    private function getProjectTypeRequirements(string $chapterType, string $projectType): array
    {
        $requirements = [
            'software' => [
                'methodology' => [
                    [
                        'type' => 'system_requirements',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'Hardware and software requirements',
                        'columns' => ['Category', 'Requirement', 'Specification', 'Purpose'],
                    ],
                    [
                        'type' => 'database_schema',
                        'required' => false,
                        'min' => 1,
                        'mock_data' => false,
                        'description' => 'Database table structure',
                        'columns' => ['Table Name', 'Field', 'Data Type', 'Constraints', 'Description'],
                    ],
                ],
                'results' => [
                    [
                        'type' => 'testing_results',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Software testing results',
                        'columns' => ['Test Case', 'Input', 'Expected Output', 'Actual Output', 'Status'],
                    ],
                    [
                        'type' => 'performance_test',
                        'required' => false,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'System performance metrics',
                        'columns' => ['Metric', 'Benchmark', 'Result', 'Status'],
                    ],
                ],
            ],
            'survey_research' => [
                'results' => [
                    [
                        'type' => 'response_rate',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Survey response rate analysis',
                        'columns' => ['Category', 'Distributed', 'Returned', 'Valid', 'Response Rate (%)'],
                    ],
                ],
            ],
            'clinical' => [
                'results' => [
                    [
                        'type' => 'patient_outcomes',
                        'required' => true,
                        'min' => 1,
                        'mock_data' => true,
                        'description' => 'Patient clinical outcomes',
                        'columns' => ['Outcome', 'Baseline', 'Follow-up', 'Change', 'p-value'],
                    ],
                ],
            ],
        ];

        return $requirements[$projectType][$chapterType] ?? [];
    }

    /**
     * Get all available table types
     */
    public function getAvailableTableTypes(): array
    {
        return [
            'component_specification',
            'pin_connections',
            'power_analysis',
            'test_results',
            'performance_metrics',
            'comparison',
            'sample_demographics',
            'variable_operationalization',
            'reliability_test',
            'descriptive_statistics',
            'hypothesis_test',
            'correlation_regression',
            'patient_demographics',
            'clinical_outcomes',
            'statistical_analysis',
            'financial_analysis',
            'experimental_data',
        ];
    }
}
