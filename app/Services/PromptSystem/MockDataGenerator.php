<?php

namespace App\Services\PromptSystem;

use App\Models\Project;

class MockDataGenerator
{
    /**
     * Generate table structure with mock data template
     */
    public function generateTableStructure(string $tableType, Project $project): ?array
    {
        return match ($tableType) {
            'sample_demographics', 'demographic_characteristics' => $this->generateDemographicsStructure($project),
            'test_results' => $this->generateTestResultsStructure($project),
            'performance_metrics' => $this->generatePerformanceMetricsStructure($project),
            'component_specification', 'bill_of_materials' => $this->generateComponentsStructure($project),
            'power_analysis', 'power_budget' => $this->generatePowerAnalysisStructure($project),
            'hypothesis_test' => $this->generateHypothesisTestStructure($project),
            'descriptive_statistics' => $this->generateDescriptiveStatsStructure($project),
            'reliability_test' => $this->generateReliabilityTestStructure($project),
            'correlation_regression', 'correlation_matrix' => $this->generateCorrelationStructure($project),
            'clinical_outcomes' => $this->generateClinicalOutcomesStructure($project),
            'experimental_data' => $this->generateExperimentalDataStructure($project),
            'growth_parameters' => $this->generateGrowthParametersStructure($project),
            'yield_components' => $this->generateYieldComponentsStructure($project),
            'financial_analysis' => $this->generateFinancialAnalysisStructure($project),
            'response_rate' => $this->generateResponseRateStructure($project),
            default => null,
        };
    }

    /**
     * Generate sample demographics table structure
     */
    private function generateDemographicsStructure(Project $project): array
    {
        return [
            'title' => 'Demographic Characteristics of Respondents',
            'headers' => ['Variable', 'Category', 'Frequency', 'Percentage (%)'],
            'sample_rows' => [
                ['Gender', 'Male', '120', '48.0'],
                ['', 'Female', '130', '52.0'],
                ['Age Group', '18-25', '85', '34.0'],
                ['', '26-35', '95', '38.0'],
                ['', '36-45', '50', '20.0'],
                ['', '46 and above', '20', '8.0'],
                ['Education', 'Secondary', '40', '16.0'],
                ['', 'Diploma/NCE', '80', '32.0'],
                ['', "Bachelor's Degree", '100', '40.0'],
                ['', 'Postgraduate', '30', '12.0'],
            ],
            'footer_note' => 'Total N = [Your sample size]',
            'data_collection_guide' => [
                'Enter demographic data from questionnaire Section A into SPSS',
                'Run frequency analysis: Analyze > Descriptive Statistics > Frequencies',
                'Select all demographic variables',
                'Export the frequency table to your document',
                'Calculate percentage: (frequency / total N) × 100',
            ],
        ];
    }

    /**
     * Generate test results table structure
     */
    private function generateTestResultsStructure(Project $project): array
    {
        return [
            'title' => 'System Functional Test Results',
            'headers' => ['Test Case', 'Expected Result', 'Actual Result', 'Deviation (%)', 'Status'],
            'sample_rows' => [
                ['Sensor Reading Accuracy', '25.0°C', '24.8°C', '0.8%', 'PASS'],
                ['Response Time', '< 100ms', '85ms', '-', 'PASS'],
                ['Power Consumption', '< 500mA', '480mA', '4%', 'PASS'],
                ['Display Update', 'Real-time', 'Real-time', '-', 'PASS'],
                ['Communication Range', '> 10m', '12m', '+20%', 'PASS'],
            ],
            'footer_note' => 'Tests conducted under controlled laboratory conditions',
            'data_collection_guide' => [
                'Set up test environment with calibrated equipment',
                'Document test conditions (temperature, humidity, etc.)',
                'Run each test at least 3 times',
                'Record all measurements with timestamps',
                'Calculate average and deviation for each parameter',
                'Determine pass/fail based on requirements specification',
            ],
        ];
    }

    /**
     * Generate performance metrics structure
     */
    private function generatePerformanceMetricsStructure(Project $project): array
    {
        return [
            'title' => 'System Performance Metrics',
            'headers' => ['Metric', 'Target', 'Achieved', 'Unit', 'Remarks'],
            'sample_rows' => [
                ['Overall Accuracy', '95%', '96.5%', '%', 'Exceeded target'],
                ['Power Efficiency', '85%', '88%', '%', 'Exceeded target'],
                ['Uptime', '99%', '99.5%', '%', 'Highly reliable'],
                ['Processing Speed', '1000 samples/s', '1200 samples/s', 'samples/s', 'Exceeded target'],
            ],
            'data_collection_guide' => [
                'Define target metrics from project requirements',
                'Design test procedures for each metric',
                'Collect data under normal operating conditions',
                'Calculate achieved values using appropriate formulas',
                'Document any deviations and their causes',
            ],
        ];
    }

    /**
     * Generate components/BOM structure
     */
    private function generateComponentsStructure(Project $project): array
    {
        return [
            'title' => 'Bill of Materials',
            'headers' => ['Component', 'Model/Part No.', 'Specifications', 'Qty', 'Unit Cost (NGN)', 'Total (NGN)'],
            'sample_rows' => [
                ['Arduino Uno', 'ATmega328P', 'Microcontroller, 5V, 16MHz', '1', '15,000', '15,000'],
                ['Temperature Sensor', 'DHT22', 'Temp/Humidity, ±0.5°C', '2', '3,500', '7,000'],
                ['LCD Display', '16×2', 'I2C Interface, Blue Backlight', '1', '4,000', '4,000'],
                ['Power Supply', 'HLK-PM01', 'AC-DC, 5V/3W', '1', '2,500', '2,500'],
                ['Resistors', 'Mixed Pack', '1/4W, Various values', '1 pack', '1,000', '1,000'],
            ],
            'footer_note' => 'Prices as of [Date]. Total: NGN XX,XXX',
            'data_collection_guide' => [
                'List all components from your circuit design',
                'Get exact model numbers from datasheets',
                'Check current prices from local electronics suppliers',
                'Include shipping costs if ordering online',
                'Add 10-15% contingency for miscellaneous items',
            ],
        ];
    }

    /**
     * Generate power analysis structure
     */
    private function generatePowerAnalysisStructure(Project $project): array
    {
        return [
            'title' => 'Power Consumption Analysis',
            'headers' => ['Component', 'Voltage (V)', 'Current (mA)', 'Power (mW)', 'Operating Mode'],
            'sample_rows' => [
                ['Microcontroller', '5.0', '50', '250', 'Active'],
                ['Microcontroller', '5.0', '0.1', '0.5', 'Sleep'],
                ['Temperature Sensor', '3.3', '1.5', '5.0', 'Active'],
                ['LCD Display', '5.0', '25', '125', 'Backlight On'],
                ['WiFi Module', '3.3', '80', '264', 'Transmitting'],
                ['WiFi Module', '3.3', '20', '66', 'Idle'],
            ],
            'footer_note' => 'Total power consumption (worst case): XXX mW',
            'data_collection_guide' => [
                'Get current consumption values from component datasheets',
                'Measure actual current using multimeter in series',
                'Calculate power using P = V × I',
                'Consider different operating modes',
                'Sum up for total power budget',
                'Ensure power supply can handle peak demand',
            ],
        ];
    }

    /**
     * Generate hypothesis test structure
     */
    private function generateHypothesisTestStructure(Project $project): array
    {
        return [
            'title' => 'Summary of Hypothesis Testing Results',
            'headers' => ['Hypothesis', 'Test Used', 'Test Statistic', 'df', 'p-value', 'Decision'],
            'sample_rows' => [
                ['H1: There is a significant relationship between X and Y', 'Pearson Correlation', 'r = 0.65', '248', '0.001', 'Reject H₀'],
                ['H2: There is a significant difference in Z between groups', 'Independent t-test', 't = 2.84', '198', '0.005', 'Reject H₀'],
                ['H3: X significantly predicts Y', 'Linear Regression', 'F = 45.2', '1, 248', '0.000', 'Reject H₀'],
                ['H4: There is association between A and B', 'Chi-Square', 'χ² = 12.5', '3', '0.006', 'Reject H₀'],
            ],
            'footer_note' => 'Significance level α = 0.05. Reject H₀ if p < 0.05',
            'data_collection_guide' => [
                'State null and alternative hypotheses clearly',
                'Choose appropriate test based on variable types',
                'Run test in SPSS: Analyze > [Appropriate Test]',
                'Record test statistic, df, and exact p-value',
                'Compare p-value with significance level (α = 0.05)',
                'State decision: Reject or Fail to Reject H₀',
            ],
        ];
    }

    /**
     * Generate descriptive statistics structure
     */
    private function generateDescriptiveStatsStructure(Project $project): array
    {
        return [
            'title' => 'Descriptive Statistics for Study Variables',
            'headers' => ['Variable', 'N', 'Mean', 'Std. Deviation', 'Interpretation'],
            'sample_rows' => [
                ['Job Satisfaction', '250', '3.85', '0.72', 'High'],
                ['Organizational Commitment', '250', '3.62', '0.81', 'Moderate-High'],
                ['Work-Life Balance', '250', '3.45', '0.88', 'Moderate'],
                ['Employee Engagement', '250', '3.78', '0.69', 'High'],
            ],
            'footer_note' => 'Scale: 1-5 (1=Strongly Disagree, 5=Strongly Agree). Mean > 3.5 = High',
            'data_collection_guide' => [
                'Enter Likert scale responses into SPSS',
                'Compute mean scores for each variable/scale',
                'Run: Analyze > Descriptive Statistics > Descriptives',
                'Select all study variables',
                'Check Mean, Std. Deviation, Min, Max',
                'Interpret means against scale midpoint (3.0 for 5-point scale)',
            ],
        ];
    }

    /**
     * Generate reliability test structure
     */
    private function generateReliabilityTestStructure(Project $project): array
    {
        return [
            'title' => 'Reliability Test Results (Cronbach\'s Alpha)',
            'headers' => ['Scale/Section', 'No. of Items', "Cronbach's Alpha", 'Interpretation'],
            'sample_rows' => [
                ['Job Satisfaction', '8', '0.85', 'Good'],
                ['Organizational Commitment', '6', '0.82', 'Good'],
                ['Work-Life Balance', '5', '0.78', 'Acceptable'],
                ['Employee Engagement', '7', '0.88', 'Good'],
                ['Overall Instrument', '26', '0.91', 'Excellent'],
            ],
            'footer_note' => 'Interpretation: α > 0.9 Excellent, > 0.8 Good, > 0.7 Acceptable',
            'data_collection_guide' => [
                'Conduct pilot test with 30-50 respondents',
                'Enter pilot data into SPSS',
                'Run: Analyze > Scale > Reliability Analysis',
                'Select items for each subscale',
                'Check "Scale if item deleted" for item analysis',
                'Record Cronbach\'s Alpha for each scale',
                'Remove items if they significantly lower alpha',
            ],
        ];
    }

    /**
     * Generate correlation structure
     */
    private function generateCorrelationStructure(Project $project): array
    {
        return [
            'title' => 'Correlation Matrix of Study Variables',
            'headers' => ['Variable', '1', '2', '3', '4'],
            'sample_rows' => [
                ['1. Job Satisfaction', '1', '', '', ''],
                ['2. Org. Commitment', '0.65**', '1', '', ''],
                ['3. Work-Life Balance', '0.48**', '0.52**', '1', ''],
                ['4. Engagement', '0.72**', '0.68**', '0.55**', '1'],
            ],
            'footer_note' => '** Correlation is significant at p < 0.01 (2-tailed)',
            'data_collection_guide' => [
                'Compute mean scores for all variables',
                'Run: Analyze > Correlate > Bivariate',
                'Select all study variables',
                'Check Pearson correlation coefficient',
                'Flag significant correlations at 0.05 and 0.01 levels',
                'Interpretation: 0-0.3 weak, 0.3-0.7 moderate, > 0.7 strong',
            ],
        ];
    }

    /**
     * Generate clinical outcomes structure
     */
    private function generateClinicalOutcomesStructure(Project $project): array
    {
        return [
            'title' => 'Pre and Post Intervention Clinical Outcomes',
            'headers' => ['Outcome Variable', 'Pre Mean (SD)', 'Post Mean (SD)', 't-value', 'p-value', 'Effect Size (d)'],
            'sample_rows' => [
                ['Blood Pressure (mmHg)', '145.2 (12.5)', '128.6 (10.2)', '8.42', '0.001', '1.45'],
                ['BMI (kg/m²)', '28.5 (3.2)', '26.8 (2.9)', '4.56', '0.001', '0.56'],
                ['Knowledge Score', '12.5 (3.8)', '18.2 (2.5)', '-10.25', '0.001', '1.77'],
                ['Self-Efficacy', '45.3 (8.2)', '62.5 (7.1)', '-12.8', '0.001', '2.24'],
            ],
            'footer_note' => 'Paired samples t-test. Effect size: 0.2 small, 0.5 medium, 0.8 large',
            'data_collection_guide' => [
                'Collect baseline (pre) data before intervention',
                'Administer intervention according to protocol',
                'Collect post-intervention data at specified time point',
                'Run: Analyze > Compare Means > Paired Samples T-Test',
                'Calculate effect size: d = (Mean₁ - Mean₂) / SDpooled',
                'Interpret clinical significance along with statistical significance',
            ],
        ];
    }

    /**
     * Generate experimental data structure
     */
    private function generateExperimentalDataStructure(Project $project): array
    {
        return [
            'title' => 'Experimental Results',
            'headers' => ['Sample/Trial', 'Measurement 1', 'Measurement 2', 'Measurement 3', 'Mean', 'Std. Dev'],
            'sample_rows' => [
                ['Control', '45.2', '44.8', '45.5', '45.17', '0.35'],
                ['Treatment A', '52.3', '51.8', '53.1', '52.40', '0.66'],
                ['Treatment B', '58.7', '59.2', '58.5', '58.80', '0.36'],
                ['Treatment C', '64.1', '63.5', '64.8', '64.13', '0.65'],
            ],
            'footer_note' => 'Three replicates per treatment',
            'data_collection_guide' => [
                'Prepare samples according to experimental protocol',
                'Calibrate measurement equipment',
                'Take triplicate measurements for each sample',
                'Record all readings immediately',
                'Calculate mean and standard deviation',
                'Check for outliers using appropriate tests',
            ],
        ];
    }

    /**
     * Generate growth parameters structure (Agriculture)
     */
    private function generateGrowthParametersStructure(Project $project): array
    {
        return [
            'title' => 'Effect of Treatments on Growth Parameters',
            'headers' => ['Treatment', 'Plant Height (cm)', 'No. of Leaves', 'Leaf Area (cm²)', 'Days to Maturity'],
            'sample_rows' => [
                ['Control', '45.2c', '12.5b', '125.3c', '95.0a'],
                ['NPK 100%', '62.5b', '15.8a', '185.6b', '88.0b'],
                ['NPK 150%', '68.3a', '16.2a', '210.5a', '85.0c'],
                ['Organic', '58.7b', '14.5ab', '165.2b', '92.0ab'],
                ['LSD (0.05)', '5.2', '1.8', '18.5', '4.5'],
            ],
            'footer_note' => 'Means followed by the same letter are not significantly different at p < 0.05',
            'data_collection_guide' => [
                'Take measurements at specified growth stages',
                'Sample 5-10 plants randomly from each plot',
                'Use standard measurement protocols',
                'Enter data into GenStat, SAS, or R',
                'Run ANOVA and mean separation tests',
                'Assign letters based on LSD or Duncan test',
            ],
        ];
    }

    /**
     * Generate yield components structure (Agriculture)
     */
    private function generateYieldComponentsStructure(Project $project): array
    {
        return [
            'title' => 'Effect of Treatments on Yield and Yield Components',
            'headers' => ['Treatment', 'Pods/Plant', 'Seeds/Pod', '100-Seed Wt (g)', 'Yield (kg/ha)'],
            'sample_rows' => [
                ['Control', '18.5c', '2.5b', '12.8c', '1,250c'],
                ['NPK 100%', '28.3b', '3.2a', '15.5b', '1,850b'],
                ['NPK 150%', '32.5a', '3.4a', '16.8a', '2,150a'],
                ['Organic', '25.2b', '3.0a', '14.5b', '1,680b'],
                ['LSD (0.05)', '3.5', '0.4', '1.2', '185'],
            ],
            'footer_note' => 'Means followed by the same letter are not significantly different at p < 0.05',
            'data_collection_guide' => [
                'Harvest from net plot area only',
                'Count pods from 10 representative plants',
                'Shell pods and count seeds per pod',
                'Weigh 100 seeds from each sample',
                'Weigh total harvest and convert to kg/ha',
                'Adjust yield to standard moisture content if needed',
            ],
        ];
    }

    /**
     * Generate financial analysis structure
     */
    private function generateFinancialAnalysisStructure(Project $project): array
    {
        return [
            'title' => 'Financial Performance Metrics',
            'headers' => ['Metric', 'Year 1', 'Year 2', 'Year 3', 'Growth Rate (%)'],
            'sample_rows' => [
                ['Revenue (M NGN)', '125.5', '158.2', '195.8', '24.8'],
                ['Gross Profit (M NGN)', '45.2', '58.6', '75.2', '28.8'],
                ['Net Profit (M NGN)', '18.5', '25.2', '35.8', '38.2'],
                ['ROI (%)', '12.5', '15.8', '18.5', '-'],
            ],
            'footer_note' => 'Growth rate calculated as compound annual growth rate (CAGR)',
            'data_collection_guide' => [
                'Obtain financial statements from the organization',
                'Extract relevant figures for each year',
                'Calculate growth rates: ((End/Start)^(1/n) - 1) × 100',
                'Calculate key ratios (ROI, profit margins)',
                'Compare with industry benchmarks if available',
            ],
        ];
    }

    /**
     * Generate response rate structure
     */
    private function generateResponseRateStructure(Project $project): array
    {
        return [
            'title' => 'Survey Response Rate',
            'headers' => ['Category', 'Distributed', 'Returned', 'Valid', 'Response Rate (%)'],
            'sample_rows' => [
                ['Managers', '50', '45', '43', '86.0'],
                ['Supervisors', '80', '72', '68', '85.0'],
                ['Staff', '170', '145', '139', '81.8'],
                ['Total', '300', '262', '250', '83.3'],
            ],
            'footer_note' => 'Response rate above 70% is generally considered acceptable',
            'data_collection_guide' => [
                'Keep a log of all questionnaires distributed',
                'Track return date for each questionnaire',
                'Screen returned questionnaires for completeness',
                'Calculate response rate: (Valid/Distributed) × 100',
                'Document reasons for non-response if known',
            ],
        ];
    }
}
