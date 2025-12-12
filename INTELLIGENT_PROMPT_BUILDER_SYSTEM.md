The plan is now complete. I've created a comprehensive document that:
Analyzes current prompt system strengths and weaknesses
Proposes a multi-level routing architecture (Faculty → Department → Course → Field of Study → Topic)
Details the ContentDecisionEngine for smart table/diagram decisions
Shows MockDataGenerator for generating realistic sample data with replacement instructions
Details PlaceholderInstructionBuilder for detailed user guidance
Lists all files to create and modify
Provides implementation steps phased over ~14 days
Shows before/after comparison of output quality
I should now call ExitPlanMode since the plan is complete and the user has already answered my clarifying questions.
I've completed the comprehensive plan for improving your chapter generation prompt system. The plan includes:
Key Features of the New System:
Multi-Level Context Routing - Routes prompts based on Faculty → Department → Course → Field of Study → Topic keywords
ContentDecisionEngine - Smart logic that decides:
How many tables are needed per chapter type
What type of diagrams are required
Whether to generate mock data or placeholders
Which tools to recommend
MockDataGenerator - Creates realistic sample data with:
Clear ⚠️ THIS IS SAMPLE DATA warnings
Step-by-step instructions to collect real data
Nigerian context (local suppliers, ₦ prices)
PlaceholderInstructionBuilder - Detailed user guidance with:
What to show in diagrams
Which tools to use (Fritzing, EasyEDA, SPSS, etc.)
Step-by-step creation guides
Export specifications
Faculty-Specific Templates - Dedicated templates for:
Engineering (circuit diagrams, component specs, code)
Social Sciences (surveys, statistics, qualitative)
Healthcare (clinical data, patient outcomes)
Business (financial analysis, SWOT)
And more...
The plan creates ~30 new files organized in app/Services/PromptSystem/ and modifies 4 existing files.
Claude’s Plan
Chapter Generation Prompt Analysis & Improvement Plan
Executive Summary
This plan analyzes the current chapter generation prompt system and proposes a comprehensive multi-level academic context routing system where each discipline gets its own specialized prompts. The system will route based on Faculty → Department → Course → Field of Study, generating mock data with clear replacement instructions and dynamic tool recommendations.
Part 1: Current Implementation Analysis
Files Involved
File	Purpose
ChapterController.php	Main prompt building (buildProgressivePrompt, getFacultySpecificInstructions)
ProjectTypeDetector.php	Project type detection & context-aware instructions
ChapterGuidanceService.php	AI-powered guidance generation
AIContentGenerator.php	Multi-provider AI generation with fallback
FacultyStructureService.php	Faculty-specific chapter structures
What's Implemented Well
1. Project Type Detection (ProjectTypeDetector.php)
Detects 8 project types: SOFTWARE, HARDWARE, RESEARCH, DESIGN, BUSINESS, SCIENCE, HEALTHCARE, GENERAL
Uses keyword scoring + faculty fallback
Maps capabilities per type (can_generate_code, can_generate_diagrams, etc.)
2. Content Capabilities by Type
self::TYPE_SOFTWARE => [
    'can_generate_code' => true,
    'can_generate_diagrams' => true, // Mermaid
    'needs_screenshots' => true,
    'diagram_types' => ['flowchart', 'sequence', 'class', 'erd', 'architecture'],
],
self::TYPE_HARDWARE => [
    'needs_circuit_diagrams' => true,
    // ...
]
3. Placeholder System (Already Exists)
Screenshot placeholders with instructions
Circuit diagram placeholders with tools (Fritzing, CircuitLab, EasyEDA)
Format: [FIGURE X.X: Title] with **Instructions for Student:**
4. Mermaid Diagram Generation
Supports flowcharts, sequence, class, ERD diagrams
Instructions included in prompts
5. Faculty-Specific Requirements
Different citation counts by faculty (Science: 60-80, Engineering: 50-70, Social Sciences: 70-90)
Different emphasis (experiments vs surveys vs case studies)
What's Missing or Weak
1. No Intelligent Table Decision Logic
Current State: AI decides ad-hoc when to add tables Problem: No guidance on:
WHEN tables are needed (data presentation, comparisons, specifications)
HOW MANY tables minimum for each chapter type
Whether data should be PLACEHOLDER or GENERATED
Instructions for users to obtain real data
2. No Data Source Instructions for Placeholders
Current State: Placeholders exist but don't guide users on obtaining real data Problem: User receives [TABLE 3.2: Survey Results] but no guidance on:
What survey instrument to use
How to collect data
Where to find real data sources
Step-by-step data collection process
3. Generic Prompts Regardless of Academic Context
Current State: Single prompt path with some faculty adjustments Problem:
Engineering projects need circuit diagrams, component specs, calculations
Social Science needs survey instruments, qualitative analysis frameworks
Healthcare needs patient data tables, care pathways, clinical protocols
All currently use same base prompt with minor tweaks
4. Weak Chapter-Type Intelligence
Current State: Chapter type detected (Introduction, Methodology, Results) Problem: Not enough differentiation in what each chapter type MUST contain:
Methodology for Engineering: circuit design, component selection, power analysis
Methodology for Social Science: sampling, survey design, interview protocols
Results for Engineering: performance metrics tables, test data
Results for Social Science: statistical analysis tables, qualitative themes
5. No Smart Decision-Making Framework
Current State: Instructions are static Problem: AI doesn't dynamically decide:
"This section claims X, needs a table to support it"
"This is engineering design, MUST have circuit diagram placeholder"
"This methodology describes sampling, needs sample size calculation table"
Part 2: Implementation Architecture
Multi-Level Academic Context Routing System
The system will route prompts through a hierarchical context matching:
┌─────────────────────────────────────────────────────────────┐
│                    ROUTING HIERARCHY                        │
├─────────────────────────────────────────────────────────────┤
│  Level 1: FACULTY (Engineering, Science, Social Science...) │
│      ↓                                                      │
│  Level 2: DEPARTMENT (Electrical Eng, Computer Science...)  │
│      ↓                                                      │
│  Level 3: COURSE (Power Systems, Software Engineering...)   │
│      ↓                                                      │
│  Level 4: FIELD OF STUDY (Renewable Energy, AI/ML...)       │
│      ↓                                                      │
│  Level 5: PROJECT TOPIC (specific keyword matching)         │
└─────────────────────────────────────────────────────────────┘
Data Storage Architecture
New Database Table: prompt_templates
CREATE TABLE prompt_templates (
    id BIGINT PRIMARY KEY,
    context_type ENUM('faculty', 'department', 'course', 'field_of_study', 'topic_keyword'),
    context_value VARCHAR(255),          -- e.g., "engineering", "electrical_engineering"
    parent_template_id BIGINT NULL,      -- For inheritance/override
    chapter_type VARCHAR(50),            -- 'introduction', 'methodology', 'results', etc.

    -- Content requirements
    table_requirements JSON,             -- min tables, types, when to use placeholders
    diagram_requirements JSON,           -- required diagrams per chapter
    calculation_requirements JSON,       -- formulas, step-by-step format
    code_requirements JSON,              -- when to include code, language
    placeholder_rules JSON,              -- what needs placeholder vs generated

    -- Tool recommendations
    recommended_tools JSON,              -- tools by diagram/content type

    -- The actual prompt template
    system_prompt TEXT,
    chapter_prompt_template TEXT,

    -- Metadata
    priority INT DEFAULT 0,              -- Higher = more specific match
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
Directory Structure
app/Services/PromptSystem/
├── PromptRouter.php                    # Main router service
├── ContextMatcher.php                  # Matches project to templates
├── PromptBuilder.php                   # Builds final prompt from templates
├── ContentDecisionEngine.php           # Decides tables/diagrams/placeholders
├── MockDataGenerator.php               # Generates realistic mock data
├── PlaceholderInstructionBuilder.php   # Builds detailed placeholder instructions
│
├── Templates/
│   ├── PromptTemplateInterface.php
│   ├── BasePromptTemplate.php
│   │
│   ├── Faculty/
│   │   ├── EngineeringTemplate.php
│   │   ├── SocialScienceTemplate.php
│   │   ├── HealthcareTemplate.php
│   │   ├── BusinessTemplate.php
│   │   ├── ScienceTemplate.php
│   │   ├── ArtsTemplate.php
│   │   ├── EducationTemplate.php
│   │   ├── LawTemplate.php
│   │   └── AgricultureTemplate.php
│   │
│   ├── ChapterType/
│   │   ├── IntroductionTemplate.php
│   │   ├── LiteratureReviewTemplate.php
│   │   ├── MethodologyTemplate.php
│   │   ├── ResultsTemplate.php
│   │   ├── DiscussionTemplate.php
│   │   └── ConclusionTemplate.php
│   │
│   └── Specialized/
│       ├── CircuitDesignTemplate.php       # For electronics projects
│       ├── SoftwareDesignTemplate.php      # For software projects
│       ├── SurveyResearchTemplate.php      # For survey-based research
│       ├── ExperimentalTemplate.php        # For lab-based research
│       ├── CaseStudyTemplate.php           # For case study research
│       └── ClinicalTemplate.php            # For healthcare/nursing
│
├── Requirements/
│   ├── TableRequirements.php               # Table decision logic
│   ├── DiagramRequirements.php             # Diagram decision logic
│   ├── CalculationRequirements.php         # When/how to show calculations
│   └── ToolRecommendations.php             # Dynamic tool suggestions
│
└── MockData/
    ├── MockDataFactory.php
    ├── SurveyMockData.php
    ├── ExperimentalMockData.php
    ├── FinancialMockData.php
    └── EngineeringMockData.php
Part 3: Detailed Implementation
3.1 PromptRouter Service
class PromptRouter
{
    public function buildPrompt(Project $project, int $chapterNumber): string
    {
        // 1. Match context from most specific to least specific
        $contexts = $this->contextMatcher->match($project);
        // Returns: ['faculty' => 'engineering', 'department' => 'electrical',
        //           'course' => 'power_systems', 'field' => 'renewable_energy']

        // 2. Load templates with inheritance (more specific overrides general)
        $template = $this->loadTemplateChain($contexts, $chapterNumber);

        // 3. Determine content requirements
        $requirements = $this->contentDecisionEngine->analyze($project, $chapterNumber, $template);

        // 4. Build the prompt with all components
        return $this->promptBuilder->build($project, $chapterNumber, $template, $requirements);
    }
}
3.2 ContentDecisionEngine - Smart Table/Diagram Logic
class ContentDecisionEngine
{
    public function analyze(Project $project, int $chapterNumber, PromptTemplate $template): ContentRequirements
    {
        $chapterType = $this->detectChapterType($chapterNumber, $template);
        $projectType = $this->projectTypeDetector->detect($project);

        return new ContentRequirements(
            tables: $this->determineTableRequirements($chapterType, $projectType, $project),
            diagrams: $this->determineDiagramRequirements($chapterType, $projectType, $project),
            calculations: $this->determineCalculationRequirements($chapterType, $projectType),
            code: $this->determineCodeRequirements($chapterType, $projectType),
            mockData: $this->determineMockDataNeeds($chapterType, $projectType),
            placeholders: $this->determinePlaceholderNeeds($chapterType, $projectType),
            tools: $this->recommendTools($chapterType, $projectType),
        );
    }

    private function determineTableRequirements(string $chapterType, string $projectType, Project $project): array
    {
        $rules = [
            // METHODOLOGY CHAPTER
            'methodology' => [
                'engineering' => [
                    ['type' => 'component_specification', 'required' => true, 'min' => 1,
                     'description' => 'List of components with specifications, quantity, cost'],
                    ['type' => 'pin_connections', 'required' => true, 'min' => 1,
                     'description' => 'Pin connection mapping between components'],
                    ['type' => 'power_analysis', 'required' => true, 'min' => 1,
                     'description' => 'Power consumption of each component'],
                ],
                'social_science' => [
                    ['type' => 'sample_demographics', 'required' => true, 'min' => 1,
                     'mock_data' => true, 'description' => 'Demographics of study participants'],
                    ['type' => 'variable_operationalization', 'required' => true, 'min' => 1,
                     'description' => 'Variables, indicators, measurement scales'],
                    ['type' => 'reliability_test', 'required' => true, 'min' => 1,
                     'mock_data' => true, 'description' => 'Cronbach Alpha for instrument reliability'],
                ],
                'healthcare' => [
                    ['type' => 'patient_demographics', 'required' => true, 'min' => 1,
                     'mock_data' => true, 'description' => 'Patient demographics (anonymized)'],
                    ['type' => 'assessment_instrument', 'required' => true, 'min' => 1,
                     'description' => 'Assessment tool/questionnaire structure'],
                    ['type' => 'intervention_protocol', 'required' => false, 'min' => 1,
                     'description' => 'Intervention steps and timeline'],
                ],
            ],

            // RESULTS CHAPTER
            'results' => [
                'engineering' => [
                    ['type' => 'test_results', 'required' => true, 'min' => 2,
                     'mock_data' => true, 'description' => 'Performance test results'],
                    ['type' => 'performance_metrics', 'required' => true, 'min' => 1,
                     'mock_data' => true, 'description' => 'Efficiency, accuracy, speed metrics'],
                    ['type' => 'comparison', 'required' => false, 'min' => 1,
                     'mock_data' => true, 'description' => 'Comparison with existing solutions'],
                ],
                'social_science' => [
                    ['type' => 'descriptive_statistics', 'required' => true, 'min' => 1,
                     'mock_data' => true, 'description' => 'Mean, SD, frequency distributions'],
                    ['type' => 'hypothesis_test', 'required' => true, 'min' => 1,
                     'mock_data' => true, 'description' => 'Statistical test results (t-test, ANOVA, chi-square)'],
                    ['type' => 'correlation_regression', 'required' => false, 'min' => 1,
                     'mock_data' => true, 'description' => 'Correlation matrix or regression results'],
                ],
                'healthcare' => [
                    ['type' => 'clinical_outcomes', 'required' => true, 'min' => 2,
                     'mock_data' => true, 'description' => 'Pre/post intervention outcomes'],
                    ['type' => 'statistical_analysis', 'required' => true, 'min' => 1,
                     'mock_data' => true, 'description' => 'Statistical significance of outcomes'],
                ],
            ],
        ];

        return $rules[$chapterType][$projectType] ?? $rules[$chapterType]['general'] ?? [];
    }
}
3.3 MockDataGenerator - Realistic Sample Data
class MockDataGenerator
{
    public function generateTable(string $tableType, Project $project): array
    {
        return match($tableType) {
            'sample_demographics' => $this->generateDemographicsTable($project),
            'test_results' => $this->generateTestResultsTable($project),
            'component_specification' => $this->generateComponentTable($project),
            'clinical_outcomes' => $this->generateClinicalOutcomes($project),
            // ...
        };
    }

    private function generateDemographicsTable(Project $project): array
    {
        // Generate based on research type
        return [
            'title' => 'Table 4.1: Demographic Characteristics of Respondents (N=150)',
            'headers' => ['Variable', 'Category', 'Frequency', 'Percentage (%)'],
            'data' => [
                ['Gender', 'Male', '78', '52.0'],
                ['Gender', 'Female', '72', '48.0'],
                ['Age', '18-25', '45', '30.0'],
                ['Age', '26-35', '62', '41.3'],
                ['Age', '36-45', '28', '18.7'],
                ['Age', '46 and above', '15', '10.0'],
                ['Education', 'Secondary', '35', '23.3'],
                ['Education', 'Tertiary', '98', '65.3'],
                ['Education', 'Postgraduate', '17', '11.4'],
            ],
            'note' => '⚠️ THIS IS SAMPLE DATA. Replace with your actual survey data.',
            'instructions' => [
                '1. Collect demographic information from your questionnaire',
                '2. Use SPSS or Excel to calculate frequencies',
                '3. Calculate percentages: (frequency/total) × 100',
                '4. Update the table with your actual values',
            ],
        ];
    }

    private function generateTestResultsTable(Project $project): array
    {
        // For engineering projects
        return [
            'title' => 'Table 4.2: System Performance Test Results',
            'headers' => ['Test Parameter', 'Expected Value', 'Measured Value', 'Deviation (%)', 'Status'],
            'data' => [
                ['Response Time', '< 100ms', '85ms', '-15%', 'PASS'],
                ['Accuracy', '> 95%', '97.3%', '+2.3%', 'PASS'],
                ['Power Consumption', '< 500mA', '420mA', '-16%', 'PASS'],
                ['Operating Range', '0-50°C', '5-45°C', 'Within range', 'PASS'],
                ['Error Rate', '< 2%', '1.2%', '-0.8%', 'PASS'],
            ],
            'note' => '⚠️ THIS IS SAMPLE DATA. Replace with your actual test measurements.',
            'instructions' => [
                '1. Define your test parameters based on system requirements',
                '2. Set up test equipment (multimeter, oscilloscope, etc.)',
                '3. Run each test at least 3 times for consistency',
                '4. Calculate average values and deviation from expected',
                '5. Document test conditions (temperature, voltage, etc.)',
            ],
        ];
    }
}
3.4 PlaceholderInstructionBuilder - Detailed User Guidance
class PlaceholderInstructionBuilder
{
    public function buildDiagramPlaceholder(string $diagramType, Project $project, array $context): string
    {
        return match($diagramType) {
            'circuit_diagram' => $this->buildCircuitDiagramPlaceholder($project, $context),
            'system_architecture' => $this->buildArchitecturePlaceholder($project, $context),
            'flowchart' => $this->buildFlowchartPlaceholder($project, $context),
            'survey_instrument' => $this->buildSurveyInstrumentPlaceholder($project, $context),
            'screenshot' => $this->buildScreenshotPlaceholder($project, $context),
            default => $this->buildGenericPlaceholder($diagramType, $project, $context),
        };
    }

    private function buildCircuitDiagramPlaceholder(Project $project, array $context): string
    {
        $components = $context['components'] ?? ['microcontroller', 'sensors', 'display'];
        $topic = $project->topic;

        return <<<PLACEHOLDER
┌────────────────────────────────────────────────────────────────────────┐
│  [FIGURE {$context['figure_number']}: Circuit Diagram for {$topic}]   │
├────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ⚠️ THIS REQUIRES A CIRCUIT DIAGRAM THAT YOU MUST CREATE               │
│                                                                         │
│  📋 WHAT TO SHOW IN THIS DIAGRAM:                                      │
│  • Main microcontroller/processor connections                           │
│  • Sensor/input device connections with pin numbers                     │
│  • Output device connections (display, actuators, etc.)                 │
│  • Power supply circuit (voltage regulator, capacitors)                 │
│  • All component values (resistors in Ω, capacitors in µF)             │
│                                                                         │
│  🛠️ RECOMMENDED TOOLS (Choose one):                                    │
│  1. Fritzing (FREE) - fritzing.org                                     │
│     • Best for: Arduino, breadboard designs                            │
│     • Output: Breadboard view + Schematic                              │
│                                                                         │
│  2. EasyEDA (FREE) - easyeda.com                                       │
│     • Best for: Professional PCB designs                               │
│     • Output: Schematic + PCB layout                                   │
│                                                                         │
│  3. Proteus (Academic License) - labcenter.com                         │
│     • Best for: Simulation + Schematic                                 │
│     • Output: Simulated circuit behavior                               │
│                                                                         │
│  📐 STEP-BY-STEP CREATION GUIDE:                                       │
│  1. Open your chosen software                                          │
│  2. Add components from library:                                       │
│     - Search for: {implode(', ', $components)}                         │
│  3. Connect components according to your design                        │
│  4. Add component values and labels                                    │
│  5. Add power and ground connections                                   │
│  6. Verify connections match your actual hardware                      │
│  7. Export as PNG (minimum 1200px width)                               │
│                                                                         │
│  📏 EXPORT SPECIFICATIONS:                                              │
│  • Format: PNG or PDF                                                  │
│  • Resolution: At least 1200px width                                   │
│  • Background: White                                                   │
│  • Include: Component labels, pin numbers, values                      │
│                                                                         │
└────────────────────────────────────────────────────────────────────────┘
PLACEHOLDER;
    }
}
3.5 ToolRecommendations - Dynamic Tool Suggestions
class ToolRecommendations
{
    public function getTools(string $projectType, string $contentType): array
    {
        $tools = [
            'engineering' => [
                'circuit_diagram' => [
                    ['name' => 'Fritzing', 'url' => 'fritzing.org', 'cost' => 'Free', 'best_for' => 'Arduino, breadboard prototypes'],
                    ['name' => 'EasyEDA', 'url' => 'easyeda.com', 'cost' => 'Free', 'best_for' => 'PCB design, professional schematics'],
                    ['name' => 'Proteus', 'url' => 'labcenter.com', 'cost' => 'Academic license', 'best_for' => 'Simulation, microcontrollers'],
                    ['name' => 'KiCad', 'url' => 'kicad.org', 'cost' => 'Free', 'best_for' => 'Professional PCB, open source'],
                ],
                'code' => [
                    ['name' => 'Arduino IDE', 'url' => 'arduino.cc', 'best_for' => 'Arduino programming'],
                    ['name' => 'PlatformIO', 'url' => 'platformio.org', 'best_for' => 'Multi-platform embedded'],
                    ['name' => 'VS Code', 'url' => 'code.visualstudio.com', 'best_for' => 'General programming'],
                ],
                'simulation' => [
                    ['name' => 'MATLAB/Simulink', 'best_for' => 'Control systems, signal processing'],
                    ['name' => 'LTspice', 'url' => 'analog.com', 'cost' => 'Free', 'best_for' => 'Circuit simulation'],
                ],
            ],
            'social_science' => [
                'statistics' => [
                    ['name' => 'SPSS', 'best_for' => 'Survey analysis, hypothesis testing'],
                    ['name' => 'R/RStudio', 'url' => 'rstudio.com', 'cost' => 'Free', 'best_for' => 'Advanced statistics'],
                    ['name' => 'JASP', 'url' => 'jasp-stats.org', 'cost' => 'Free', 'best_for' => 'Beginner-friendly statistics'],
                ],
                'survey' => [
                    ['name' => 'Google Forms', 'cost' => 'Free', 'best_for' => 'Quick surveys'],
                    ['name' => 'SurveyMonkey', 'best_for' => 'Professional surveys'],
                    ['name' => 'Kobo Toolbox', 'cost' => 'Free', 'best_for' => 'Field data collection'],
                ],
                'qualitative' => [
                    ['name' => 'NVivo', 'best_for' => 'Thematic analysis, coding'],
                    ['name' => 'ATLAS.ti', 'best_for' => 'Qualitative data analysis'],
                ],
            ],
            'healthcare' => [
                'statistics' => [
                    ['name' => 'SPSS', 'best_for' => 'Clinical data analysis'],
                    ['name' => 'Epi Info', 'url' => 'cdc.gov/epiinfo', 'cost' => 'Free', 'best_for' => 'Epidemiological analysis'],
                    ['name' => 'R/RStudio', 'cost' => 'Free', 'best_for' => 'Biostatistics'],
                ],
                'assessment' => [
                    ['name' => 'REDCap', 'best_for' => 'Clinical data capture'],
                    ['name' => 'ODK', 'cost' => 'Free', 'best_for' => 'Mobile health data collection'],
                ],
            ],
            'business' => [
                'financial' => [
                    ['name' => 'Microsoft Excel', 'best_for' => 'Financial modeling, projections'],
                    ['name' => 'Google Sheets', 'cost' => 'Free', 'best_for' => 'Collaborative analysis'],
                ],
                'analysis' => [
                    ['name' => 'SPSS', 'best_for' => 'Market research analysis'],
                    ['name' => 'Tableau', 'best_for' => 'Data visualization'],
                ],
            ],
        ];

        return $tools[$projectType][$contentType] ?? [];
    }
}
Part 4: Files to Create/Modify
New Files to Create
Core Services
File	Purpose
app/Services/PromptSystem/PromptRouter.php	Main routing service
app/Services/PromptSystem/ContextMatcher.php	Match project to templates
app/Services/PromptSystem/PromptBuilder.php	Build final prompt
app/Services/PromptSystem/ContentDecisionEngine.php	Table/diagram decision logic
app/Services/PromptSystem/MockDataGenerator.php	Generate realistic mock data
app/Services/PromptSystem/PlaceholderInstructionBuilder.php	User guidance for placeholders
Faculty Templates
File	Purpose
app/Services/PromptSystem/Templates/PromptTemplateInterface.php	Interface
app/Services/PromptSystem/Templates/BasePromptTemplate.php	Abstract base
app/Services/PromptSystem/Templates/Faculty/EngineeringTemplate.php	Engineering-specific
app/Services/PromptSystem/Templates/Faculty/SocialScienceTemplate.php	Social science
app/Services/PromptSystem/Templates/Faculty/HealthcareTemplate.php	Healthcare/Nursing
app/Services/PromptSystem/Templates/Faculty/BusinessTemplate.php	Business/Management
app/Services/PromptSystem/Templates/Faculty/ScienceTemplate.php	Pure sciences
app/Services/PromptSystem/Templates/Faculty/ArtsTemplate.php	Arts/Humanities
app/Services/PromptSystem/Templates/Faculty/EducationTemplate.php	Education
app/Services/PromptSystem/Templates/Faculty/LawTemplate.php	Law
app/Services/PromptSystem/Templates/Faculty/AgricultureTemplate.php	Agriculture
Chapter Type Templates
File	Purpose
app/Services/PromptSystem/Templates/ChapterType/IntroductionTemplate.php	Chapter 1 logic
app/Services/PromptSystem/Templates/ChapterType/LiteratureReviewTemplate.php	Chapter 2 logic
app/Services/PromptSystem/Templates/ChapterType/MethodologyTemplate.php	Chapter 3 logic
app/Services/PromptSystem/Templates/ChapterType/ResultsTemplate.php	Chapter 4 logic
app/Services/PromptSystem/Templates/ChapterType/DiscussionTemplate.php	Chapter 5 logic
app/Services/PromptSystem/Templates/ChapterType/ConclusionTemplate.php	Final chapter logic
Requirements & Mock Data
File	Purpose
app/Services/PromptSystem/Requirements/TableRequirements.php	Table decision rules
app/Services/PromptSystem/Requirements/DiagramRequirements.php	Diagram decision rules
app/Services/PromptSystem/Requirements/ToolRecommendations.php	Dynamic tool suggestions
app/Services/PromptSystem/MockData/MockDataFactory.php	Factory for mock data
app/Services/PromptSystem/MockData/SurveyMockData.php	Survey data generator
app/Services/PromptSystem/MockData/EngineeringMockData.php	Engineering data generator
Database Migration
File	Purpose
database/migrations/xxxx_create_prompt_templates_table.php	Prompt templates table
Files to Modify
File	Changes
app/Http/Controllers/ChapterController.php	Replace buildProgressivePrompt() with PromptRouter
app/Services/ProjectTypeDetector.php	Enhance with multi-level context detection
app/Jobs/GenerateChapter.php	Use new prompt system
app/Services/ChapterGuidanceService.php	Align with new routing system
Part 5: Implementation Steps
Phase 1: Foundation (Days 1-2)
Create database migration for prompt_templates table
Create PromptTemplateInterface and BasePromptTemplate
Create ContextMatcher with multi-level matching logic
Create PromptRouter service
Phase 2: Content Decision Engine (Days 3-4)
Build ContentDecisionEngine with table/diagram rules
Create TableRequirements with rules per chapter type × project type
Create DiagramRequirements with similar structure
Create ToolRecommendations mapping
Phase 3: Faculty Templates (Days 5-7)
Create EngineeringTemplate (most complex - circuit, code, calculations)
Create SocialScienceTemplate (survey, statistics, qualitative)
Create HealthcareTemplate (clinical, patient data, interventions)
Create BusinessTemplate (financial, SWOT, market analysis)
Create remaining faculty templates
Phase 4: Mock Data & Placeholders (Days 8-9)
Build MockDataGenerator with realistic sample data
Build PlaceholderInstructionBuilder with detailed guides
Create specific mock data classes per project type
Phase 5: Integration (Days 10-11)
Modify ChapterController.php to use new system
Modify GenerateChapter.php job
Add seeder for initial prompt templates
Phase 6: Testing & Refinement (Days 12-14)
Test with sample engineering project
Test with sample social science project
Test with sample healthcare project
Refine prompts based on output quality
Part 6: Example Output Comparison
Current Output (Vague)
[TABLE 3.1: Component List]
| Component | Description |
|-----------|-------------|
| Arduino   | Microcontroller |
| Sensor    | Temperature sensor |
New Output (Detailed with Instructions)
**Table 3.1: Bill of Materials - System Components**

| Component | Model/Part No. | Specifications | Quantity | Unit Cost (₦) | Total (₦) |
|-----------|---------------|----------------|----------|---------------|-----------|
| Microcontroller | Arduino Uno R3 | ATmega328P, 16MHz, 5V | 1 | 8,500 | 8,500 |
| Temperature Sensor | DHT22 | -40 to 80°C, ±0.5°C accuracy | 1 | 3,200 | 3,200 |
| LCD Display | 16x2 I2C | HD44780, 5V, Blue backlight | 1 | 2,500 | 2,500 |
| Resistor | - | 10kΩ, 1/4W, 5% tolerance | 3 | 50 | 150 |
| Capacitor | - | 100µF, 16V, electrolytic | 2 | 100 | 200 |
| Power Supply | LM7805 | 5V, 1A regulator | 1 | 350 | 350 |
| **Total** | | | | | **14,900** |

⚠️ **THIS IS SAMPLE DATA** - Replace with your actual component list and prices.

📋 **How to Complete This Table:**
1. List all components used in your actual circuit design
2. Get part numbers from datasheets or supplier websites
3. Check prices from local suppliers (e.g., Roban Electronics, Kano Market)
4. Include shipping/delivery costs if applicable
5. Add 10% contingency for damaged/spare components

🛒 **Where to Get Components in Nigeria:**
- Roban Electronics (Lagos): robanelectronics.com
- Kits and Parts (online): kitsandparts.net
- Jumia Tech: jumia.com.ng/electronics
Summary
This comprehensive implementation creates an intelligent, multi-level prompt routing system that:
Routes based on context - Faculty → Department → Course → Field of Study → Topic
Makes smart decisions - Knows when tables/diagrams are needed per chapter type
Generates mock data - Realistic sample data with clear replacement instructions
Provides detailed guidance - Step-by-step instructions for creating placeholders
Recommends tools dynamically - Based on project type and content needs
Maintains academic rigor - Discipline-specific requirements and standards
Stayed in plan mode
start implementation phase by phase
The user wants me to start implementing the plan phase by phase. Let me begin with Phase 1: Foundation, which includes:
Create database migration for prompt_templates table
Create PromptTemplateInterface and BasePromptTemplate
Create ContextMatcher with multi-level matching logic
Create PromptRouter service
Let me start implementing these files.
I'll start implementing the prompt system phase by phase. Let me begin with Phase 1: Foundation.
Update Todos

Phase 1: Create database migration for prompt_templates table

Phase 1: Create PromptTemplateInterface and BasePromptTemplate

Phase 1: Create ContextMatcher with multi-level matching

Phase 1: Create PromptRouter service

Phase 2: Build ContentDecisionEngine with table/diagram rules

Phase 2: Create TableRequirements class

Phase 2: Create DiagramRequirements class

Phase 2: Create ToolRecommendations mapping

Phase 3: Create Faculty Templates (Engineering, Social Science, Healthcare, Business)

Phase 4: Build MockDataGenerator and PlaceholderInstructionBuilder

Phase 5: Integrate with ChapterController and GenerateChapter job
Let me start creating the database migration for prompt_templates table.
Bash Create prompt_templates migration
IN
php artisan make:migration create_prompt_templates_table --no-interaction
OUT
   INFO  Migration [database/migrations/2025_12_11_213514_create_prompt_templates_table.php] created successfully.
Bash Create PromptSystem directory structure
IN
mkdir -p app/Services/PromptSystem/Templates/Faculty app/Services/PromptSystem/Templates/ChapterType app/Services/PromptSystem/Templates/Specialized app/Services/PromptSystem/Requirements app/Services/PromptSystem/MockData
OUT
0
Now let me write the migration file and start creating the services.
Globpattern: "database/migrations/*create_prompt_templates_table.php"
Found 1 file
Let me read and then edit the migration file to add the proper schema.
Read 2025_12_11_213514_create_prompt_templates_table.php
Let me update the migration with the full schema:
Edit 2025_12_11_213514_create_prompt_templates_table.php
Added 34 lines
dont' forget if u are going to run migration use docker compose exec app
Now let me run the migration, then create the PromptTemplateInterface and BasePromptTemplate classes:
Now let me create the core services:
Bash Run prompt_templates migration
IN
php artisan migrate --no-interaction
OUT
Exit code 1

   Illuminate\Database\QueryException 

  SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for mysql failed: Temporary failure in name resolution (Connection: mysql, SQL: select exists (select 1 from information_schema.tables where table_schema = schema() and table_name = 'migrations' and table_type in ('BASE TABLE', 'SYSTEM VERSIONED')) as `exists`)

  at vendor/laravel/framework/src/Illuminate/Database/Connection.php:824
    820▕                     $this->getName(), $query, $this->prepareBindings($bindings), $e
    821▕                 );
    822▕             }
    823▕ 
  ➜ 824▕             throw new QueryException(
    825▕                 $this->getName(), $query, $this->prepareBindings($bindings), $e
    826▕             );
    827▕         }
    828▕     }

      [2m+42 vendor frames [22m

  43  artisan:16
      Illuminate\Foundation\Application::handleCommand()
Bash Run migration via docker
IN
docker compose exec app php artisan migrate --no-interaction
OUT
   INFO  Running migrations.  

  2025_12_11_213514_create_prompt_templates_table .............. 360.33ms DONE
Migration ran successfully. Now let me create the core services - PromptTemplateInterface and BasePromptTemplate:
Now let me create the PromptTemplateInterface and core services:
Write PromptTemplateInterface.php
60 lines
<?php

namespace App\Services\PromptSystem\Templates;

use App\Models\Project;
use App\Services\PromptSystem\ContentRequirements;

interface PromptTemplateInterface
{
    /**
     * Get the system prompt for AI context
     */
    public function getSystemPrompt(): string;

    /**
     * Build the chapter-specific prompt
     */
    public function buildChapterPrompt(Project $project, int $chapterNumber, ContentRequirements $requirements): string;

    /**
     * Get table requirements for this template
     */
    public function getTableRequirements(int $chapterNumber): array;

    /**
     * Get diagram requirements for this template
     */
    public function getDiagramRequirements(int $chapterNumber): array;

    /**
     * Get calculation requirements for this template
     */
    public function getCalculationRequirements(int $chapterNumber): array;

    /**
     * Get code requirements for this template
     */
    public function getCodeRequirements(int $chapterNumber): array;

    /**
     * Get placeholder rules for this template
     */
    public function getPlaceholderRules(int $chapterNumber): array;

    /**
     * Get recommended tools for this template
     */
    public function getRecommendedTools(): array;

    /**
     * Get the template priority (higher = more specific)
     */
    public function getPriority(): int;

    /**
     * Check if this template supports the given chapter type
     */
    public function supportsChapterType(string $chapterType): bool;
}
Now let me create the ContentRequirements class, then the BasePromptTemplate:
Write ContentRequirements.php
161 lines
<?php

namespace App\Services\PromptSystem;

class ContentRequirements
{
    public function __construct(
        public array $tables = [],
        public array $diagrams = [],
        public array $calculations = [],
        public array $code = [],
        public array $mockData = [],
        public array $placeholders = [],
        public array $tools = [],
        public array $citations = [],
        public array $formatting = []
    ) {}

    /**
     * Get all table requirements
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * Get required tables only
     */
    public function getRequiredTables(): array
    {
        return array_filter($this->tables, fn ($table) => $table['required'] ?? false);
    }

    /**
     * Get tables that need mock data
     */
    public function getMockDataTables(): array
    {
        return array_filter($this->tables, fn ($table) => $table['mock_data'] ?? false);
    }

    /**
     * Get all diagram requirements
     */
    public function getDiagrams(): array
    {
        return $this->diagrams;
    }

    /**
     * Get required diagrams only
     */
    public function getRequiredDiagrams(): array
    {
        return array_filter($this->diagrams, fn ($diagram) => $diagram['required'] ?? false);
    }

    /**
     * Get diagrams that need placeholders (can't be AI-generated)
     */
    public function getPlaceholderDiagrams(): array
    {
        return array_filter($this->diagrams, fn ($diagram) => $diagram['needs_placeholder'] ?? false);
    }

    /**
     * Check if code is required for this chapter
     */
    public function requiresCode(): bool
    {
        return ! empty($this->code) && ($this->code['required'] ?? false);
    }

    /**
     * Get code language if code is required
     */
    public function getCodeLanguage(): ?string
    {
        return $this->code['language'] ?? null;
    }

    /**
     * Check if calculations are required
     */
    public function requiresCalculations(): bool
    {
        return ! empty($this->calculations);
    }

    /**
     * Get minimum table count
     */
    public function getMinimumTableCount(): int
    {
        return array_sum(array_map(fn ($t) => $t['min'] ?? 1, $this->getRequiredTables()));
    }

    /**
     * Get minimum diagram count
     */
    public function getMinimumDiagramCount(): int
    {
        return array_sum(array_map(fn ($d) => $d['min'] ?? 1, $this->getRequiredDiagrams()));
    }

    /**
     * Merge with another ContentRequirements (for template inheritance)
     */
    public function merge(ContentRequirements $other): self
    {
        return new self(
            tables: array_merge($this->tables, $other->tables),
            diagrams: array_merge($this->diagrams, $other->diagrams),
            calculations: array_merge($this->calculations, $other->calculations),
            code: array_merge($this->code, $other->code),
            mockData: array_merge($this->mockData, $other->mockData),
            placeholders: array_merge($this->placeholders, $other->placeholders),
            tools: array_merge($this->tools, $other->tools),
            citations: array_merge($this->citations, $other->citations),
            formatting: array_merge($this->formatting, $other->formatting)
        );
    }

    /**
     * Convert to array for JSON storage
     */
    public function toArray(): array
    {
        return [
            'tables' => $this->tables,
            'diagrams' => $this->diagrams,
            'calculations' => $this->calculations,
            'code' => $this->code,
            'mockData' => $this->mockData,
            'placeholders' => $this->placeholders,
            'tools' => $this->tools,
            'citations' => $this->citations,
            'formatting' => $this->formatting,
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tables: $data['tables'] ?? [],
            diagrams: $data['diagrams'] ?? [],
            calculations: $data['calculations'] ?? [],
            code: $data['code'] ?? [],
            mockData: $data['mockData'] ?? [],
            placeholders: $data['placeholders'] ?? [],
            tools: $data['tools'] ?? [],
            citations: $data['citations'] ?? [],
            formatting: $data['formatting'] ?? []
        );
    }
}
Now let me create the BasePromptTemplate abstract class that implements common functionality:
Write BasePromptTemplate.php
582 lines
<?php

namespace App\Services\PromptSystem\Templates;

use App\Models\Project;
use App\Services\PromptSystem\ContentRequirements;

abstract class BasePromptTemplate implements PromptTemplateInterface
{
    protected int $priority = 0;

    protected array $supportedChapterTypes = [
        'introduction',
        'literature_review',
        'methodology',
        'results',
        'discussion',
        'conclusion',
    ];

    /**
     * Get the base system prompt - can be extended by child classes
     */
    public function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert academic writer specializing in generating comprehensive, publication-ready academic content.

CORE PRINCIPLES:
1. Write in formal academic third-person style - NEVER use "I", "we", "my", "our"
2. Use phrases like "this study", "the research", "the analysis", "the findings"
3. Generate detailed, substantive content that meets word count requirements
4. Include proper academic citations in APA format
5. Create well-structured sections with clear headings

FORMATTING RULES:
- Use numbered section headings (e.g., 3.1, 3.2, 3.1.1)
- Never use "&" - always write "and"
- Use bullet points (•) not dashes (-) for lists
- Spell out chapter numbers: "CHAPTER ONE" not "CHAPTER 1"

CONTENT QUALITY:
- Each section must be comprehensive and well-developed
- Provide specific examples, evidence, and explanations
- Ensure logical flow between paragraphs and sections
- Include transitions between major sections
PROMPT;
    }

    /**
     * Build the chapter prompt - combines base structure with specific requirements
     */
    public function buildChapterPrompt(Project $project, int $chapterNumber, ContentRequirements $requirements): string
    {
        $prompt = $this->buildProjectContext($project, $chapterNumber);
        $prompt .= $this->buildChapterTypeInstructions($chapterNumber);
        $prompt .= $this->buildTableInstructions($requirements);
        $prompt .= $this->buildDiagramInstructions($requirements);
        $prompt .= $this->buildCalculationInstructions($requirements);
        $prompt .= $this->buildCodeInstructions($requirements);
        $prompt .= $this->buildPlaceholderInstructions($requirements);
        $prompt .= $this->buildFormattingInstructions($project, $chapterNumber);

        return $prompt;
    }

    /**
     * Build project context section
     */
    protected function buildProjectContext(Project $project, int $chapterNumber): string
    {
        $targetWordCount = $this->getTargetWordCount($project, $chapterNumber);

        return <<<CONTEXT

PROJECT CONTEXT:
- Topic: {$project->topic}
- Faculty: {$project->faculty}
- Department: {$project->department}
- Course: {$project->course}
- Field of Study: {$project->field_of_study}
- Academic Level: {$project->type}
- University: {$project->university}

CHAPTER REQUIREMENTS:
- Chapter Number: {$chapterNumber}
- Target Word Count: {$targetWordCount} words (MANDATORY - do not stop early)

CONTEXT;
    }

    /**
     * Build chapter type specific instructions
     */
    protected function buildChapterTypeInstructions(int $chapterNumber): string
    {
        $chapterType = $this->detectChapterType($chapterNumber);

        return match ($chapterType) {
            'introduction' => $this->getIntroductionInstructions(),
            'literature_review' => $this->getLiteratureReviewInstructions(),
            'methodology' => $this->getMethodologyInstructions(),
            'results' => $this->getResultsInstructions(),
            'discussion' => $this->getDiscussionInstructions(),
            'conclusion' => $this->getConclusionInstructions(),
            default => $this->getGenericChapterInstructions(),
        };
    }

    /**
     * Build table generation instructions
     */
    protected function buildTableInstructions(ContentRequirements $requirements): string
    {
        $tables = $requirements->getTables();
        if (empty($tables)) {
            return '';
        }

        $instructions = "\n\nTABLE REQUIREMENTS:\n";
        $instructions .= "This chapter MUST include the following tables:\n\n";

        foreach ($tables as $index => $table) {
            $tableNum = $index + 1;
            $required = ($table['required'] ?? false) ? '(REQUIRED)' : '(Recommended)';
            $instructions .= "Table {$tableNum}: {$table['type']} {$required}\n";
            $instructions .= "- Description: {$table['description']}\n";

            if ($table['mock_data'] ?? false) {
                $instructions .= "- Generate realistic SAMPLE DATA with clear warning: '⚠️ THIS IS SAMPLE DATA - Replace with your actual data'\n";
                $instructions .= "- Include step-by-step instructions for collecting real data\n";
            }

            if (! empty($table['columns'])) {
                $instructions .= '- Required columns: '.implode(', ', $table['columns'])."\n";
            }

            $instructions .= "\n";
        }

        return $instructions;
    }

    /**
     * Build diagram generation instructions
     */
    protected function buildDiagramInstructions(ContentRequirements $requirements): string
    {
        $diagrams = $requirements->getDiagrams();
        if (empty($diagrams)) {
            return '';
        }

        $instructions = "\n\nDIAGRAM REQUIREMENTS:\n";

        foreach ($diagrams as $diagram) {
            $required = ($diagram['required'] ?? false) ? '(REQUIRED)' : '(Recommended)';

            if ($diagram['can_generate'] ?? false) {
                $instructions .= "\n{$diagram['type']} {$required} - Generate using Mermaid syntax:\n";
                $instructions .= "```mermaid\n{$diagram['format']}\n```\n";
            } else {
                $instructions .= "\n{$diagram['type']} {$required} - Create PLACEHOLDER with instructions:\n";
                $instructions .= "- What to show: {$diagram['description']}\n";
                $instructions .= "- Recommended tool: {$diagram['tool']}\n";
                $instructions .= "- Include step-by-step creation guide\n";
            }
        }

        return $instructions;
    }

    /**
     * Build calculation instructions
     */
    protected function buildCalculationInstructions(ContentRequirements $requirements): string
    {
        if (! $requirements->requiresCalculations()) {
            return '';
        }

        $calculations = $requirements->calculations;

        $instructions = "\n\nCALCULATION REQUIREMENTS:\n";
        $instructions .= "Show all calculations with step-by-step workings:\n\n";
        $instructions .= "FORMAT FOR EACH CALCULATION:\n";
        $instructions .= "1. State the formula\n";
        $instructions .= "2. Define all variables with values\n";
        $instructions .= "3. Show substitution step\n";
        $instructions .= "4. Show arithmetic operations\n";
        $instructions .= "5. State final result with units\n";
        $instructions .= "6. Explain what the result means\n\n";

        if (! empty($calculations['types'])) {
            $instructions .= 'Required calculation types: '.implode(', ', $calculations['types'])."\n";
        }

        return $instructions;
    }

    /**
     * Build code generation instructions
     */
    protected function buildCodeInstructions(ContentRequirements $requirements): string
    {
        if (! $requirements->requiresCode()) {
            return '';
        }

        $code = $requirements->code;
        $language = $code['language'] ?? 'relevant programming language';

        $instructions = "\n\nCODE REQUIREMENTS:\n";
        $instructions .= "Include {$language} code with:\n";
        $instructions .= "- Proper syntax highlighting\n";
        $instructions .= "- Inline comments explaining logic\n";
        $instructions .= "- Function/method documentation\n";
        $instructions .= "- Working, realistic code (not pseudocode)\n\n";

        if (! empty($code['snippets'])) {
            $instructions .= "Required code sections:\n";
            foreach ($code['snippets'] as $snippet) {
                $instructions .= "- {$snippet}\n";
            }
        }

        return $instructions;
    }

    /**
     * Build placeholder instructions
     */
    protected function buildPlaceholderInstructions(ContentRequirements $requirements): string
    {
        $placeholders = $requirements->placeholders;
        if (empty($placeholders)) {
            return '';
        }

        $instructions = "\n\nPLACEHOLDER REQUIREMENTS:\n";
        $instructions .= "For content that cannot be AI-generated, create detailed placeholders:\n\n";
        $instructions .= "FORMAT:\n";
        $instructions .= "[FIGURE X.X: Title]\n";
        $instructions .= "⚠️ THIS REQUIRES [TYPE] THAT YOU MUST CREATE\n\n";
        $instructions .= "📋 WHAT TO SHOW:\n";
        $instructions .= "• [Specific details]\n\n";
        $instructions .= "🛠️ RECOMMENDED TOOLS:\n";
        $instructions .= "• [Tool name] - [URL] - [Best for]\n\n";
        $instructions .= "📐 STEP-BY-STEP GUIDE:\n";
        $instructions .= "1. [First step]\n";
        $instructions .= "2. [Second step]\n";
        $instructions .= "...\n\n";

        return $instructions;
    }

    /**
     * Build formatting instructions
     */
    protected function buildFormattingInstructions(Project $project, int $chapterNumber): string
    {
        return <<<FORMATTING

FORMATTING INSTRUCTIONS:
- Use section numbering: {$chapterNumber}.1, {$chapterNumber}.2, {$chapterNumber}.1.1, etc.
- Format headings as: '{$chapterNumber}.1 Section Title'
- Use proper academic table format with captions
- Reference all tables and figures in the text
- Use APA citation format: (Author, Year)

CRITICAL REMINDERS:
- Write in THIRD PERSON only
- Never use "&" - write "and"
- Use bullet points (•) not dashes (-)
- Generate comprehensive content to meet word count
- Include realistic sample data with replacement instructions

FORMATTING;
    }

    /**
     * Get target word count for chapter
     */
    protected function getTargetWordCount(Project $project, int $chapterNumber): int
    {
        // Default word counts by chapter type
        $defaults = [
            1 => 2500,  // Introduction
            2 => 5000,  // Literature Review
            3 => 3500,  // Methodology
            4 => 4000,  // Results
            5 => 3000,  // Discussion/Conclusion
        ];

        return $defaults[$chapterNumber] ?? 3000;
    }

    /**
     * Detect chapter type from number
     */
    protected function detectChapterType(int $chapterNumber): string
    {
        return match ($chapterNumber) {
            1 => 'introduction',
            2 => 'literature_review',
            3 => 'methodology',
            4 => 'results',
            5 => 'discussion',
            default => 'general',
        };
    }

    /**
     * Get introduction chapter instructions
     */
    protected function getIntroductionInstructions(): string
    {
        return <<<'INTRO'

CHAPTER TYPE: INTRODUCTION

REQUIRED SECTIONS:
1.1 Background of the Study
    - Broad context narrowing to specific topic
    - Recent statistics and trends
    - Relevance to the field

1.2 Statement of the Problem
    - Clear problem definition
    - Evidence of the problem
    - Gap in current knowledge

1.3 Research Objectives
    - General objective
    - Specific objectives (3-5, using action verbs)

1.4 Research Questions/Hypotheses
    - Aligned with objectives
    - Clear and answerable

1.5 Significance of the Study
    - Theoretical contribution
    - Practical implications
    - Beneficiaries

1.6 Scope and Delimitations
    - What is covered
    - What is NOT covered
    - Justification for boundaries

INTRO;
    }

    /**
     * Get literature review instructions
     */
    protected function getLiteratureReviewInstructions(): string
    {
        return <<<'LITREV'

CHAPTER TYPE: LITERATURE REVIEW

REQUIRED SECTIONS:
2.1 Conceptual Review
    - Key concepts defined
    - Theoretical underpinnings

2.2 Theoretical Framework
    - Relevant theories explained
    - Application to current study

2.3 Empirical Review
    - Previous studies reviewed
    - Organized thematically (NOT chronologically)
    - Critical analysis, not just description
    - Show relationships between studies

2.4 Research Gap
    - What is missing in literature
    - How this study addresses the gap

2.5 Conceptual Framework (if applicable)
    - Visual model of relationships
    - Explanation of framework

CITATION REQUIREMENTS:
- Minimum 40-60 recent sources
- Mix of theoretical and empirical
- Critical analysis of each source

LITREV;
    }

    /**
     * Get methodology instructions - to be overridden by faculty templates
     */
    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY

REQUIRED SECTIONS:
3.1 Research Design
    - Type of research design
    - Justification for choice

3.2 Population and Sample
    - Target population defined
    - Sampling technique
    - Sample size with justification

3.3 Data Collection
    - Instruments/tools used
    - Procedure for data collection

3.4 Data Analysis
    - Analysis techniques
    - Software used (if any)

3.5 Ethical Considerations
    - Consent procedures
    - Confidentiality measures

METHOD;
    }

    /**
     * Get results instructions
     */
    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS/FINDINGS

REQUIRED SECTIONS:
4.1 Data Presentation
    - Organized by research question/objective
    - Tables and figures with proper captions

4.2 Data Analysis
    - Statistical analysis (if quantitative)
    - Thematic analysis (if qualitative)

4.3 Interpretation of Findings
    - What the results mean
    - Comparison with objectives

TABLE REQUIREMENTS:
- Include proper table numbering (Table 4.1, 4.2, etc.)
- Reference all tables in the text
- Generate sample data with clear replacement instructions

RESULTS;
    }

    /**
     * Get discussion instructions
     */
    protected function getDiscussionInstructions(): string
    {
        return <<<'DISCUSSION'

CHAPTER TYPE: DISCUSSION

REQUIRED SECTIONS:
5.1 Summary of Findings
    - Key findings restated

5.2 Discussion of Findings
    - Compare with literature review
    - Explain agreements/disagreements
    - Provide possible explanations

5.3 Implications
    - Theoretical implications
    - Practical implications

5.4 Limitations
    - Study limitations acknowledged
    - How they affect interpretation

DISCUSSION;
    }

    /**
     * Get conclusion instructions
     */
    protected function getConclusionInstructions(): string
    {
        return <<<'CONCLUSION'

CHAPTER TYPE: CONCLUSION

REQUIRED SECTIONS:
5.X Conclusion
    - Brief summary of the study
    - Key findings highlighted
    - Research questions answered

5.X Recommendations
    - Practical recommendations
    - Policy recommendations (if applicable)
    - Recommendations for future research

CONCLUSION;
    }

    /**
     * Get generic chapter instructions
     */
    protected function getGenericChapterInstructions(): string
    {
        return "\n\nProvide comprehensive content for this chapter following standard academic structure.\n";
    }

    /**
     * Default table requirements - to be overridden by child classes
     */
    public function getTableRequirements(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default diagram requirements - to be overridden by child classes
     */
    public function getDiagramRequirements(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default calculation requirements - to be overridden by child classes
     */
    public function getCalculationRequirements(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default code requirements - to be overridden by child classes
     */
    public function getCodeRequirements(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default placeholder rules - to be overridden by child classes
     */
    public function getPlaceholderRules(int $chapterNumber): array
    {
        return [];
    }

    /**
     * Default tool recommendations - to be overridden by child classes
     */
    public function getRecommendedTools(): array
    {
        return [];
    }

    /**
     * Get template priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Check if template supports chapter type
     */
    public function supportsChapterType(string $chapterType): bool
    {
        return in_array($chapterType, $this->supportedChapterTypes);
    }
}
Now let me update the todo list and create the ContextMatcher service:
Update Todos

Phase 1: Create database migration for prompt_templates table

Phase 1: Create PromptTemplateInterface and BasePromptTemplate

Phase 1: Create ContextMatcher with multi-level matching

Phase 1: Create PromptRouter service

Phase 2: Build ContentDecisionEngine with table/diagram rules

Phase 2: Create TableRequirements class

Phase 2: Create DiagramRequirements class

Phase 2: Create ToolRecommendations mapping

Phase 3: Create Faculty Templates (Engineering, Social Science, Healthcare, Business)

Phase 4: Build MockDataGenerator and PlaceholderInstructionBuilder

Phase 5: Integrate with ChapterController and GenerateChapter job
Now let me create the ContextMatcher service that handles multi-level matching:
Write ContextMatcher.php
273 lines
<?php

namespace App\Services\PromptSystem;

use App\Models\Project;

class ContextMatcher
{
    /**
     * Faculty keyword mappings for detection
     */
    private const FACULTY_KEYWORDS = [
        'engineering' => ['engineering', 'technology', 'technical'],
        'social_science' => ['social science', 'sociology', 'psychology', 'political science', 'economics', 'anthropology'],
        'healthcare' => ['nursing', 'health', 'medicine', 'medical', 'pharmacy', 'public health'],
        'business' => ['business', 'management', 'accounting', 'finance', 'marketing', 'entrepreneurship'],
        'science' => ['science', 'physics', 'chemistry', 'biology', 'biochemistry', 'microbiology'],
        'arts' => ['arts', 'humanities', 'literature', 'philosophy', 'history', 'linguistics'],
        'education' => ['education', 'teaching', 'pedagogy', 'curriculum'],
        'law' => ['law', 'legal', 'jurisprudence'],
        'agriculture' => ['agriculture', 'agronomy', 'agricultural', 'farming', 'crop science'],
    ];

    /**
     * Department keyword mappings for engineering sub-types
     */
    private const ENGINEERING_DEPARTMENTS = [
        'electrical' => ['electrical', 'electronics', 'electronic', 'power', 'control systems'],
        'computer' => ['computer', 'software', 'computing', 'information technology', 'it'],
        'mechanical' => ['mechanical', 'mechatronics', 'automotive'],
        'civil' => ['civil', 'structural', 'construction', 'building'],
        'chemical' => ['chemical', 'petroleum', 'petrochemical'],
    ];

    /**
     * Project type keywords for specialized matching
     */
    private const PROJECT_TYPE_KEYWORDS = [
        'software' => ['software', 'application', 'app', 'system', 'web', 'mobile', 'database', 'api', 'blockchain'],
        'hardware' => ['circuit', 'arduino', 'microcontroller', 'embedded', 'sensor', 'iot', 'pcb', 'electronics'],
        'survey_research' => ['survey', 'questionnaire', 'respondents', 'perception', 'attitude', 'opinion'],
        'experimental' => ['experiment', 'laboratory', 'lab', 'test', 'trial'],
        'case_study' => ['case study', 'case analysis', 'organizational study'],
        'clinical' => ['patient', 'clinical', 'intervention', 'treatment', 'nursing care', 'health outcomes'],
    ];

    /**
     * Match project to academic context hierarchy
     *
     * @return array{faculty: string, department: ?string, course: ?string, field: ?string, project_type: ?string}
     */
    public function match(Project $project): array
    {
        $faculty = $this->detectFaculty($project);
        $department = $this->detectDepartment($project, $faculty);
        $projectType = $this->detectProjectType($project);

        return [
            'faculty' => $faculty,
            'department' => $department,
            'course' => $this->normalizeString($project->course),
            'field' => $this->normalizeString($project->field_of_study),
            'project_type' => $projectType,
            'academic_level' => $this->normalizeString($project->type),
        ];
    }

    /**
     * Detect faculty from project data
     */
    public function detectFaculty(Project $project): string
    {
        // Check explicit faculty field first
        $facultyField = $this->normalizeString($project->faculty ?? '');
        foreach (self::FACULTY_KEYWORDS as $faculty => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($facultyField, $keyword)) {
                    return $faculty;
                }
            }
        }

        // Check field of study
        $fieldOfStudy = $this->normalizeString($project->field_of_study ?? '');
        foreach (self::FACULTY_KEYWORDS as $faculty => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($fieldOfStudy, $keyword)) {
                    return $faculty;
                }
            }
        }

        // Check course name
        $course = $this->normalizeString($project->course ?? '');
        foreach (self::FACULTY_KEYWORDS as $faculty => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($course, $keyword)) {
                    return $faculty;
                }
            }
        }

        // Check topic for keywords
        $topic = $this->normalizeString($project->topic ?? '');
        foreach (self::FACULTY_KEYWORDS as $faculty => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($topic, $keyword)) {
                    return $faculty;
                }
            }
        }

        return 'general';
    }

    /**
     * Detect department (sub-faculty) from project data
     */
    public function detectDepartment(Project $project, string $faculty): ?string
    {
        // Only detect department for engineering (most variation)
        if ($faculty !== 'engineering') {
            return $this->normalizeString($project->department ?? null);
        }

        $searchText = $this->normalizeString(
            ($project->department ?? '').' '.
            ($project->course ?? '').' '.
            ($project->field_of_study ?? '').' '.
            ($project->topic ?? '')
        );

        foreach (self::ENGINEERING_DEPARTMENTS as $department => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($searchText, $keyword)) {
                    return $department;
                }
            }
        }

        return $this->normalizeString($project->department ?? null);
    }

    /**
     * Detect project type from topic and description
     */
    public function detectProjectType(Project $project): ?string
    {
        $searchText = $this->normalizeString(
            ($project->topic ?? '').' '.
            ($project->description ?? '').' '.
            ($project->field_of_study ?? '')
        );

        $scores = [];
        foreach (self::PROJECT_TYPE_KEYWORDS as $type => $keywords) {
            $scores[$type] = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($searchText, $keyword)) {
                    $scores[$type]++;
                }
            }
        }

        // Get highest scoring type
        arsort($scores);
        $topType = array_key_first($scores);

        return $scores[$topType] > 0 ? $topType : null;
    }

    /**
     * Get the best matching template context for a project
     * Returns contexts in order of specificity (most specific first)
     */
    public function getMatchingContexts(Project $project): array
    {
        $context = $this->match($project);
        $contexts = [];

        // Level 5: Topic keywords (most specific)
        if ($context['project_type']) {
            $contexts[] = [
                'type' => 'topic_keyword',
                'value' => $context['project_type'],
                'priority' => 50,
            ];
        }

        // Level 4: Field of study
        if ($context['field']) {
            $contexts[] = [
                'type' => 'field_of_study',
                'value' => $context['field'],
                'priority' => 40,
            ];
        }

        // Level 3: Course
        if ($context['course']) {
            $contexts[] = [
                'type' => 'course',
                'value' => $context['course'],
                'priority' => 30,
            ];
        }

        // Level 2: Department
        if ($context['department']) {
            $contexts[] = [
                'type' => 'department',
                'value' => $context['department'],
                'priority' => 20,
            ];
        }

        // Level 1: Faculty (least specific but always present)
        $contexts[] = [
            'type' => 'faculty',
            'value' => $context['faculty'],
            'priority' => 10,
        ];

        return $contexts;
    }

    /**
     * Check if project matches a specific context
     */
    public function matchesContext(Project $project, string $contextType, string $contextValue): bool
    {
        $context = $this->match($project);
        $normalizedValue = $this->normalizeString($contextValue);

        return match ($contextType) {
            'faculty' => $context['faculty'] === $normalizedValue,
            'department' => $context['department'] === $normalizedValue,
            'course' => str_contains($context['course'] ?? '', $normalizedValue),
            'field_of_study' => str_contains($context['field'] ?? '', $normalizedValue),
            'topic_keyword' => $context['project_type'] === $normalizedValue,
            default => false,
        };
    }

    /**
     * Get all detectable faculties
     */
    public function getAvailableFaculties(): array
    {
        return array_keys(self::FACULTY_KEYWORDS);
    }

    /**
     * Get all detectable project types
     */
    public function getAvailableProjectTypes(): array
    {
        return array_keys(self::PROJECT_TYPE_KEYWORDS);
    }

    /**
     * Normalize string for comparison
     */
    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtolower(trim($value));
    }
}
Now let me create the PromptRouter service that ties everything together:
Now let me create the PromptRouter service:
Write PromptRouter.php
278 lines
<?php

namespace App\Services\PromptSystem;

use App\Models\Project;
use App\Models\PromptTemplate;
use App\Services\PromptSystem\Templates\BasePromptTemplate;
use App\Services\PromptSystem\Templates\Faculty\AgricultureTemplate;
use App\Services\PromptSystem\Templates\Faculty\ArtsTemplate;
use App\Services\PromptSystem\Templates\Faculty\BusinessTemplate;
use App\Services\PromptSystem\Templates\Faculty\EducationTemplate;
use App\Services\PromptSystem\Templates\Faculty\EngineeringTemplate;
use App\Services\PromptSystem\Templates\Faculty\HealthcareTemplate;
use App\Services\PromptSystem\Templates\Faculty\LawTemplate;
use App\Services\PromptSystem\Templates\Faculty\ScienceTemplate;
use App\Services\PromptSystem\Templates\Faculty\SocialScienceTemplate;
use App\Services\PromptSystem\Templates\PromptTemplateInterface;
use Illuminate\Support\Facades\Log;

class PromptRouter
{
    /**
     * Faculty to template class mapping
     */
    private const FACULTY_TEMPLATES = [
        'engineering' => EngineeringTemplate::class,
        'social_science' => SocialScienceTemplate::class,
        'healthcare' => HealthcareTemplate::class,
        'business' => BusinessTemplate::class,
        'science' => ScienceTemplate::class,
        'arts' => ArtsTemplate::class,
        'education' => EducationTemplate::class,
        'law' => LawTemplate::class,
        'agriculture' => AgricultureTemplate::class,
    ];

    public function __construct(
        private ContextMatcher $contextMatcher,
        private ContentDecisionEngine $contentDecisionEngine,
        private PromptBuilder $promptBuilder
    ) {}

    /**
     * Build complete prompt for chapter generation
     */
    public function buildPrompt(Project $project, int $chapterNumber): string
    {
        // 1. Match project to academic context
        $context = $this->contextMatcher->match($project);

        Log::info('PromptRouter: Context matched', [
            'project_id' => $project->id,
            'context' => $context,
        ]);

        // 2. Load the appropriate template
        $template = $this->loadTemplate($context, $chapterNumber);

        // 3. Determine content requirements based on context and template
        $requirements = $this->contentDecisionEngine->analyze($project, $chapterNumber, $context, $template);

        Log::info('PromptRouter: Requirements analyzed', [
            'project_id' => $project->id,
            'chapter' => $chapterNumber,
            'tables_required' => count($requirements->getTables()),
            'diagrams_required' => count($requirements->getDiagrams()),
        ]);

        // 4. Build the final prompt
        return $this->promptBuilder->build($project, $chapterNumber, $template, $requirements);
    }

    /**
     * Get system prompt for AI context
     */
    public function getSystemPrompt(Project $project): string
    {
        $context = $this->contextMatcher->match($project);
        $template = $this->loadTemplate($context, 1); // Chapter number doesn't matter for system prompt

        return $template->getSystemPrompt();
    }

    /**
     * Load the appropriate template based on context
     */
    private function loadTemplate(array $context, int $chapterNumber): PromptTemplateInterface
    {
        // First, try to load from database (for custom/override templates)
        $dbTemplate = $this->loadFromDatabase($context, $chapterNumber);
        if ($dbTemplate) {
            return $dbTemplate;
        }

        // Fall back to code-based templates
        return $this->loadCodeTemplate($context['faculty']);
    }

    /**
     * Load template from database
     */
    private function loadFromDatabase(array $context, int $chapterNumber): ?PromptTemplateInterface
    {
        $chapterType = $this->detectChapterType($chapterNumber);
        $matchingContexts = [];

        // Build list of contexts to search for (in priority order)
        if ($context['project_type']) {
            $matchingContexts[] = ['type' => 'topic_keyword', 'value' => $context['project_type']];
        }
        if ($context['field']) {
            $matchingContexts[] = ['type' => 'field_of_study', 'value' => $context['field']];
        }
        if ($context['course']) {
            $matchingContexts[] = ['type' => 'course', 'value' => $context['course']];
        }
        if ($context['department']) {
            $matchingContexts[] = ['type' => 'department', 'value' => $context['department']];
        }
        $matchingContexts[] = ['type' => 'faculty', 'value' => $context['faculty']];

        // Find the most specific matching template
        foreach ($matchingContexts as $ctx) {
            $template = PromptTemplate::where('context_type', $ctx['type'])
                ->where('context_value', $ctx['value'])
                ->where('is_active', true)
                ->where(function ($query) use ($chapterType) {
                    $query->where('chapter_type', $chapterType)
                        ->orWhereNull('chapter_type');
                })
                ->orderByDesc('priority')
                ->first();

            if ($template) {
                return $this->wrapDatabaseTemplate($template, $context['faculty']);
            }
        }

        return null;
    }

    /**
     * Wrap database template in a PromptTemplateInterface
     */
    private function wrapDatabaseTemplate(PromptTemplate $dbTemplate, string $faculty): PromptTemplateInterface
    {
        // Get the base template for the faculty
        $baseTemplate = $this->loadCodeTemplate($faculty);

        // Create a wrapper that merges database config with base template
        return new class ($dbTemplate, $baseTemplate) extends BasePromptTemplate {
            public function __construct(
                private PromptTemplate $dbTemplate,
                private PromptTemplateInterface $baseTemplate
            ) {
                $this->priority = $dbTemplate->priority;
            }

            public function getSystemPrompt(): string
            {
                return $this->dbTemplate->system_prompt ?? $this->baseTemplate->getSystemPrompt();
            }

            public function buildChapterPrompt(Project $project, int $chapterNumber, ContentRequirements $requirements): string
            {
                if ($this->dbTemplate->chapter_prompt_template) {
                    // Use database template with variable substitution
                    return $this->substituteVariables(
                        $this->dbTemplate->chapter_prompt_template,
                        $project,
                        $chapterNumber,
                        $requirements
                    );
                }

                return $this->baseTemplate->buildChapterPrompt($project, $chapterNumber, $requirements);
            }

            public function getTableRequirements(int $chapterNumber): array
            {
                return $this->dbTemplate->table_requirements ?? $this->baseTemplate->getTableRequirements($chapterNumber);
            }

            public function getDiagramRequirements(int $chapterNumber): array
            {
                return $this->dbTemplate->diagram_requirements ?? $this->baseTemplate->getDiagramRequirements($chapterNumber);
            }

            public function getCalculationRequirements(int $chapterNumber): array
            {
                return $this->dbTemplate->calculation_requirements ?? $this->baseTemplate->getCalculationRequirements($chapterNumber);
            }

            public function getCodeRequirements(int $chapterNumber): array
            {
                return $this->dbTemplate->code_requirements ?? $this->baseTemplate->getCodeRequirements($chapterNumber);
            }

            public function getPlaceholderRules(int $chapterNumber): array
            {
                return $this->dbTemplate->placeholder_rules ?? $this->baseTemplate->getPlaceholderRules($chapterNumber);
            }

            public function getRecommendedTools(): array
            {
                return $this->dbTemplate->recommended_tools ?? $this->baseTemplate->getRecommendedTools();
            }

            private function substituteVariables(string $template, Project $project, int $chapterNumber, ContentRequirements $requirements): string
            {
                $variables = [
                    '{{topic}}' => $project->topic,
                    '{{faculty}}' => $project->faculty,
                    '{{department}}' => $project->department,
                    '{{course}}' => $project->course,
                    '{{field_of_study}}' => $project->field_of_study,
                    '{{academic_level}}' => $project->type,
                    '{{university}}' => $project->university,
                    '{{chapter_number}}' => $chapterNumber,
                ];

                return str_replace(array_keys($variables), array_values($variables), $template);
            }
        };
    }

    /**
     * Load code-based template for faculty
     */
    private function loadCodeTemplate(string $faculty): PromptTemplateInterface
    {
        $templateClass = self::FACULTY_TEMPLATES[$faculty] ?? null;

        if ($templateClass && class_exists($templateClass)) {
            return new $templateClass;
        }

        // Return a default template
        return new class extends BasePromptTemplate {
            protected int $priority = 0;
        };
    }

    /**
     * Detect chapter type from number
     */
    private function detectChapterType(int $chapterNumber): string
    {
        return match ($chapterNumber) {
            1 => 'introduction',
            2 => 'literature_review',
            3 => 'methodology',
            4 => 'results',
            5 => 'discussion',
            default => 'general',
        };
    }

    /**
     * Get matched context for debugging/logging
     */
    public function getMatchedContext(Project $project): array
    {
        return $this->contextMatcher->match($project);
    }

    /**
     * Get content requirements for a project/chapter
     */
    public function getContentRequirements(Project $project, int $chapterNumber): ContentRequirements
    {
        $context = $this->contextMatcher->match($project);
        $template = $this->loadTemplate($context, $chapterNumber);

        return $this->contentDecisionEngine->analyze($project, $chapterNumber, $context, $template);
    }
}
Now I need to create the PromptTemplate model, ContentDecisionEngine, and PromptBuilder. Let me create these:
Now let me create the PromptTemplate model and remaining Phase 1 services:
Bash Create PromptTemplate model
IN
php artisan make:model PromptTemplate --no-interaction
OUT
   INFO  Model [app/Models/PromptTemplate.php] created successfully.
Now let me update the PromptTemplate model with the proper fields:
Read PromptTemplate.php
Let me update the model with the proper fillables and casts:
Edit PromptTemplate.php
Added 100 lines
Now let me create the PromptBuilder service which actually builds the final prompt:
Now let me create the PromptBuilder and ContentDecisionEngine:
Write PromptBuilder.php
417 lines
<?php

namespace App\Services\PromptSystem;

use App\Models\Project;
use App\Services\PromptSystem\Templates\PromptTemplateInterface;

class PromptBuilder
{
    public function __construct(
        private MockDataGenerator $mockDataGenerator,
        private PlaceholderInstructionBuilder $placeholderBuilder
    ) {}

    /**
     * Build the complete prompt for chapter generation
     */
    public function build(
        Project $project,
        int $chapterNumber,
        PromptTemplateInterface $template,
        ContentRequirements $requirements
    ): string {
        $prompt = '';

        // 1. Add project context
        $prompt .= $this->buildProjectContext($project, $chapterNumber);

        // 2. Add chapter-specific instructions from template
        $prompt .= $template->buildChapterPrompt($project, $chapterNumber, $requirements);

        // 3. Add table generation instructions with mock data
        $prompt .= $this->buildTableSection($requirements, $project, $chapterNumber);

        // 4. Add diagram generation/placeholder instructions
        $prompt .= $this->buildDiagramSection($requirements, $project, $chapterNumber);

        // 5. Add calculation instructions
        $prompt .= $this->buildCalculationSection($requirements);

        // 6. Add code generation instructions
        $prompt .= $this->buildCodeSection($requirements);

        // 7. Add tool recommendations
        $prompt .= $this->buildToolRecommendations($requirements);

        // 8. Add final formatting reminders
        $prompt .= $this->buildFinalReminders($project, $chapterNumber);

        return $prompt;
    }

    /**
     * Build project context section
     */
    private function buildProjectContext(Project $project, int $chapterNumber): string
    {
        $targetWords = $this->getTargetWordCount($project, $chapterNumber);

        return <<<CONTEXT
You are writing Chapter {$chapterNumber} of an academic project.

PROJECT DETAILS:
- Topic: {$project->topic}
- Faculty: {$project->faculty}
- Department: {$project->department}
- Course: {$project->course}
- Field of Study: {$project->field_of_study}
- Academic Level: {$project->type}
- University: {$project->university}

TARGET WORD COUNT: {$targetWords} words (THIS IS MANDATORY)

CONTEXT;
    }

    /**
     * Build table section with mock data instructions
     */
    private function buildTableSection(ContentRequirements $requirements, Project $project, int $chapterNumber): string
    {
        $tables = $requirements->getTables();
        if (empty($tables)) {
            return '';
        }

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "📊 TABLE REQUIREMENTS FOR THIS CHAPTER\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $section .= "This chapter MUST include ".count($tables)." table(s):\n\n";

        $tableNumber = 1;
        foreach ($tables as $table) {
            $prefix = $chapterNumber.'.'.$tableNumber;
            $required = ($table['required'] ?? false) ? '✅ REQUIRED' : '📌 Recommended';

            $section .= "┌─────────────────────────────────────────────────────────────┐\n";
            $section .= "│ Table {$prefix}: {$table['type']} [{$required}]\n";
            $section .= "├─────────────────────────────────────────────────────────────┤\n";
            $section .= "│ Purpose: {$table['description']}\n";

            if (! empty($table['columns'])) {
                $section .= '│ Columns: '.implode(' | ', $table['columns'])."\n";
            }

            if ($table['mock_data'] ?? false) {
                $section .= "│\n";
                $section .= "│ 📋 GENERATE SAMPLE DATA with this format:\n";

                // Get mock data structure from generator
                $mockData = $this->mockDataGenerator->generateTableStructure($table['type'], $project);
                if ($mockData) {
                    $section .= "│\n";
                    $section .= $this->formatMockDataInstructions($mockData, $prefix);
                }

                $section .= "│\n";
                $section .= "│ ⚠️ ADD THIS WARNING BELOW THE TABLE:\n";
                $section .= "│ \"⚠️ THIS IS SAMPLE DATA - Replace with your actual data\"\n";
                $section .= "│\n";
                $section .= "│ 📝 DATA COLLECTION INSTRUCTIONS:\n";

                $instructions = $table['instructions'] ?? $this->getDefaultDataInstructions($table['type']);
                foreach ($instructions as $i => $instruction) {
                    $num = $i + 1;
                    $section .= "│ {$num}. {$instruction}\n";
                }
            }

            $section .= "└─────────────────────────────────────────────────────────────┘\n\n";
            $tableNumber++;
        }

        return $section;
    }

    /**
     * Build diagram section with placeholders
     */
    private function buildDiagramSection(ContentRequirements $requirements, Project $project, int $chapterNumber): string
    {
        $diagrams = $requirements->getDiagrams();
        if (empty($diagrams)) {
            return '';
        }

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "📐 DIAGRAM/FIGURE REQUIREMENTS\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $figureNumber = 1;
        foreach ($diagrams as $diagram) {
            $prefix = $chapterNumber.'.'.$figureNumber;

            if ($diagram['can_generate'] ?? false) {
                // AI can generate this diagram (e.g., Mermaid flowchart)
                $section .= "Figure {$prefix}: {$diagram['type']} (Generate using Mermaid)\n";
                $section .= "Generate this diagram using Mermaid syntax:\n";
                $section .= "```mermaid\n";
                $section .= "{$diagram['format']}\n";
                $section .= "```\n\n";
            } else {
                // AI cannot generate - create placeholder with instructions
                $section .= $this->placeholderBuilder->build(
                    $diagram['type'],
                    $project,
                    [
                        'figure_number' => $prefix,
                        'description' => $diagram['description'],
                        'tool' => $diagram['tool'] ?? null,
                        'components' => $diagram['components'] ?? [],
                    ]
                );
                $section .= "\n\n";
            }

            $figureNumber++;
        }

        return $section;
    }

    /**
     * Build calculation instructions section
     */
    private function buildCalculationSection(ContentRequirements $requirements): string
    {
        if (! $requirements->requiresCalculations()) {
            return '';
        }

        $calculations = $requirements->calculations;

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "🧮 CALCULATION REQUIREMENTS\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $section .= "Show ALL calculations with step-by-step workings:\n\n";

        $section .= "FORMAT FOR EACH CALCULATION:\n";
        $section .= "┌─────────────────────────────────────────────────────────────┐\n";
        $section .= "│ 1. State the formula:                                       │\n";
        $section .= "│    Formula: [Name] = [Mathematical expression]              │\n";
        $section .= "│                                                             │\n";
        $section .= "│ 2. Define variables:                                        │\n";
        $section .= "│    Where: [Variable] = [Value] [Units]                      │\n";
        $section .= "│                                                             │\n";
        $section .= "│ 3. Substitute values:                                       │\n";
        $section .= "│    [Formula with numbers]                                   │\n";
        $section .= "│                                                             │\n";
        $section .= "│ 4. Calculate:                                               │\n";
        $section .= "│    = [Step-by-step arithmetic]                              │\n";
        $section .= "│    = [Final result] [Units]                                 │\n";
        $section .= "│                                                             │\n";
        $section .= "│ 5. Interpret:                                               │\n";
        $section .= "│    This means [practical interpretation]                    │\n";
        $section .= "└─────────────────────────────────────────────────────────────┘\n\n";

        if (! empty($calculations['types'])) {
            $section .= "Required calculations:\n";
            foreach ($calculations['types'] as $type) {
                $section .= "• {$type}\n";
            }
            $section .= "\n";
        }

        if (! empty($calculations['examples'])) {
            $section .= "Example calculations to include:\n";
            foreach ($calculations['examples'] as $example) {
                $section .= "• {$example}\n";
            }
        }

        return $section;
    }

    /**
     * Build code generation section
     */
    private function buildCodeSection(ContentRequirements $requirements): string
    {
        if (! $requirements->requiresCode()) {
            return '';
        }

        $code = $requirements->code;
        $language = $code['language'] ?? 'appropriate programming language';

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "💻 CODE REQUIREMENTS\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $section .= "Include {$language} code with:\n";
        $section .= "• Proper syntax highlighting using ```{$language}\n";
        $section .= "• Inline comments explaining logic\n";
        $section .= "• Function/method documentation\n";
        $section .= "• Working, realistic code (NOT pseudocode)\n\n";

        if (! empty($code['snippets'])) {
            $section .= "Required code sections:\n";
            foreach ($code['snippets'] as $snippet) {
                $section .= "• {$snippet}\n";
            }
        }

        return $section;
    }

    /**
     * Build tool recommendations section
     */
    private function buildToolRecommendations(ContentRequirements $requirements): string
    {
        $tools = $requirements->tools;
        if (empty($tools)) {
            return '';
        }

        $section = "\n\n═══════════════════════════════════════════════════════════════\n";
        $section .= "🛠️ RECOMMENDED TOOLS FOR USER\n";
        $section .= "═══════════════════════════════════════════════════════════════\n\n";

        $section .= "When creating placeholders, recommend these tools:\n\n";

        foreach ($tools as $category => $toolList) {
            $section .= ucfirst($category).":\n";
            foreach ($toolList as $tool) {
                $cost = $tool['cost'] ?? 'Paid';
                $url = $tool['url'] ?? '';
                $section .= "• {$tool['name']} ({$cost})";
                if ($url) {
                    $section .= " - {$url}";
                }
                if (! empty($tool['best_for'])) {
                    $section .= "\n  Best for: {$tool['best_for']}";
                }
                $section .= "\n";
            }
            $section .= "\n";
        }

        return $section;
    }

    /**
     * Build final reminders
     */
    private function buildFinalReminders(Project $project, int $chapterNumber): string
    {
        $targetWords = $this->getTargetWordCount($project, $chapterNumber);

        return <<<REMINDERS


═══════════════════════════════════════════════════════════════
⚠️ FINAL REMINDERS - READ CAREFULLY
═══════════════════════════════════════════════════════════════

1. WORD COUNT: You MUST write at least {$targetWords} words. Do NOT stop early.

2. THIRD PERSON: NEVER use "I", "we", "my", "our". Use:
   • "This study", "The research", "The analysis"
   • "The findings indicate", "The results show"

3. CITATIONS: Use APA format (Author, Year). Mark uncertain sources as [UNVERIFIED].

4. FORMATTING:
   • Section numbers: {$chapterNumber}.1, {$chapterNumber}.2, {$chapterNumber}.1.1
   • Never use "&" - always write "and"
   • Use bullets (•) not dashes (-)

5. TABLES: Reference every table in the text BEFORE it appears.

6. SAMPLE DATA: All generated data must have the warning:
   "⚠️ THIS IS SAMPLE DATA - Replace with your actual data"

7. PLACEHOLDERS: Include detailed creation instructions for diagrams/figures
   the user must create themselves.

NOW WRITE THE COMPLETE CHAPTER:

REMINDERS;
    }

    /**
     * Format mock data instructions for embedding in prompt
     */
    private function formatMockDataInstructions(array $mockData, string $tablePrefix): string
    {
        $formatted = "│ Example structure:\n";
        $formatted .= "│ Table {$tablePrefix}: {$mockData['title']}\n";

        if (! empty($mockData['headers'])) {
            $formatted .= '│ Headers: '.implode(' | ', $mockData['headers'])."\n";
        }

        return $formatted;
    }

    /**
     * Get default data collection instructions by table type
     */
    private function getDefaultDataInstructions(string $tableType): array
    {
        return match ($tableType) {
            'sample_demographics', 'demographics' => [
                'Collect demographic data from your questionnaire Section A',
                'Enter data into SPSS or Excel',
                'Calculate frequency and percentage for each category',
                'Total should equal your sample size (N)',
            ],
            'test_results', 'performance_metrics' => [
                'Set up your test environment and equipment',
                'Run each test at least 3 times for consistency',
                'Record measurements with proper units',
                'Calculate average values and deviations',
            ],
            'component_specification', 'components' => [
                'List all components from your circuit design',
                'Get specifications from component datasheets',
                'Check current prices from local suppliers',
                'Include quantity and calculate total cost',
            ],
            'hypothesis_test', 'statistical_analysis' => [
                'Enter your survey data into SPSS',
                'Run the appropriate statistical test',
                'Record test statistic, df, and p-value',
                'State whether hypothesis is supported',
            ],
            default => [
                'Collect the required data from your research',
                'Organize data in the format shown',
                'Verify all values are accurate',
                'Update the table with your actual data',
            ],
        };
    }

    /**
     * Get target word count for chapter
     */
    private function getTargetWordCount(Project $project, int $chapterNumber): int
    {
        // Could be enhanced to use faculty structure service
        $defaults = [
            1 => 2500,
            2 => 5000,
            3 => 3500,
            4 => 4000,
            5 => 3000,
        ];

        return $defaults[$chapterNumber] ?? 3000;
    }
}
Now I need to create the ContentDecisionEngine which is the smart logic that determines what tables, diagrams, calculations, etc. are needed for each chapter type and project type:
Update Todos

Phase 1: Create database migration for prompt_templates table

Phase 1: Create PromptTemplateInterface and BasePromptTemplate

Phase 1: Create ContextMatcher with multi-level matching

Phase 1: Create PromptRouter service

Phase 2: Build ContentDecisionEngine with table/diagram rules

Phase 2: Create TableRequirements class

Phase 2: Create DiagramRequirements class

Phase 2: Create ToolRecommendations mapping

Phase 3: Create Faculty Templates (Engineering, Social Science, Healthcare, Business)

Phase 4: Build MockDataGenerator and PlaceholderInstructionBuilder

Phase 5: Integrate with ChapterController and GenerateChapter job
Now let me create the ContentDecisionEngine which is the core smart logic:
Write ContentDecisionEngine.php
377 lines
<?php

namespace App\Services\PromptSystem;

use App\Models\Project;
use App\Services\PromptSystem\Requirements\DiagramRequirements;
use App\Services\PromptSystem\Requirements\TableRequirements;
use App\Services\PromptSystem\Requirements\ToolRecommendations;
use App\Services\PromptSystem\Templates\PromptTemplateInterface;

class ContentDecisionEngine
{
    public function __construct(
        private TableRequirements $tableRequirements,
        private DiagramRequirements $diagramRequirements,
        private ToolRecommendations $toolRecommendations
    ) {}

    /**
     * Analyze project and determine all content requirements
     */
    public function analyze(
        Project $project,
        int $chapterNumber,
        array $context,
        PromptTemplateInterface $template
    ): ContentRequirements {
        $chapterType = $this->detectChapterType($chapterNumber);
        $projectType = $context['project_type'] ?? 'general';
        $faculty = $context['faculty'] ?? 'general';

        // Get requirements from each specialized class
        $tables = $this->determineTableRequirements($chapterType, $projectType, $faculty, $project, $template);
        $diagrams = $this->determineDiagramRequirements($chapterType, $projectType, $faculty, $project, $template);
        $calculations = $this->determineCalculationRequirements($chapterType, $projectType, $faculty);
        $code = $this->determineCodeRequirements($chapterType, $projectType, $faculty);
        $placeholders = $this->determinePlaceholderNeeds($chapterType, $projectType, $diagrams);
        $tools = $this->toolRecommendations->getToolsForContext($projectType, $faculty, $chapterType);

        return new ContentRequirements(
            tables: $tables,
            diagrams: $diagrams,
            calculations: $calculations,
            code: $code,
            mockData: $this->getMockDataConfig($tables),
            placeholders: $placeholders,
            tools: $tools,
            citations: $this->getCitationRequirements($chapterType, $faculty),
            formatting: $this->getFormattingRules($faculty)
        );
    }

    /**
     * Determine table requirements based on chapter type and context
     */
    private function determineTableRequirements(
        string $chapterType,
        string $projectType,
        string $faculty,
        Project $project,
        PromptTemplateInterface $template
    ): array {
        // First get requirements from the specialized TableRequirements class
        $baseRequirements = $this->tableRequirements->getRequirements($chapterType, $projectType, $faculty);

        // Merge with template-specific requirements
        $templateRequirements = $template->getTableRequirements(
            $this->chapterTypeToNumber($chapterType)
        );

        // Combine and deduplicate
        return $this->mergeRequirements($baseRequirements, $templateRequirements);
    }

    /**
     * Determine diagram requirements based on chapter type and context
     */
    private function determineDiagramRequirements(
        string $chapterType,
        string $projectType,
        string $faculty,
        Project $project,
        PromptTemplateInterface $template
    ): array {
        // Get base diagram requirements
        $baseRequirements = $this->diagramRequirements->getRequirements($chapterType, $projectType, $faculty);

        // Merge with template requirements
        $templateRequirements = $template->getDiagramRequirements(
            $this->chapterTypeToNumber($chapterType)
        );

        return $this->mergeRequirements($baseRequirements, $templateRequirements);
    }

    /**
     * Determine calculation requirements
     */
    private function determineCalculationRequirements(
        string $chapterType,
        string $projectType,
        string $faculty
    ): array {
        // Calculations are mainly needed in methodology and results chapters
        if (! in_array($chapterType, ['methodology', 'results'])) {
            return [];
        }

        $calculations = [
            'required' => false,
            'types' => [],
            'examples' => [],
        ];

        // Engineering projects
        if ($faculty === 'engineering') {
            $calculations['required'] = true;

            if ($projectType === 'hardware') {
                $calculations['types'] = [
                    'Power consumption (P = V × I)',
                    'Voltage divider (Vout = Vin × R2/(R1+R2))',
                    'Current limiting resistor',
                    'Efficiency calculation',
                ];
                $calculations['examples'] = [
                    'Calculate total power consumption of the circuit',
                    'Calculate resistor values for LED current limiting',
                    'Calculate voltage regulator output',
                ];
            } elseif ($projectType === 'software') {
                $calculations['types'] = [
                    'Time complexity analysis (Big O)',
                    'Space complexity',
                    'Performance metrics',
                ];
                $calculations['examples'] = [
                    'Calculate algorithm time complexity',
                    'Analyze database query performance',
                ];
            }
        }

        // Social science/Survey research
        if ($faculty === 'social_science' || $projectType === 'survey_research') {
            $calculations['required'] = $chapterType === 'results';
            $calculations['types'] = [
                'Sample size calculation (Yamane formula)',
                'Cronbach\'s Alpha for reliability',
                't-test for mean comparison',
                'Chi-square for association',
                'Correlation coefficient',
            ];
            $calculations['examples'] = [
                'Calculate sample size: n = N/(1+Ne²)',
                'Interpret Cronbach\'s Alpha values',
                'Calculate and interpret t-test results',
            ];
        }

        // Healthcare
        if ($faculty === 'healthcare') {
            $calculations['required'] = $chapterType === 'results';
            $calculations['types'] = [
                'BMI calculation',
                'Statistical significance (p-values)',
                'Effect size calculation',
                'Confidence intervals',
            ];
        }

        // Business
        if ($faculty === 'business') {
            $calculations['required'] = true;
            $calculations['types'] = [
                'ROI (Return on Investment)',
                'NPV (Net Present Value)',
                'Break-even analysis',
                'Growth rate calculations',
            ];
        }

        return $calculations;
    }

    /**
     * Determine code requirements
     */
    private function determineCodeRequirements(
        string $chapterType,
        string $projectType,
        string $faculty
    ): array {
        // Code is mainly needed in methodology and results for technical projects
        if (! in_array($chapterType, ['methodology', 'results'])) {
            return [];
        }

        // Only engineering/software projects typically need code
        if ($faculty !== 'engineering' || ! in_array($projectType, ['software', 'hardware'])) {
            return [];
        }

        $code = [
            'required' => true,
            'language' => 'auto',
            'snippets' => [],
        ];

        if ($projectType === 'software') {
            $code['language'] = 'php/javascript/python';
            $code['snippets'] = [
                'Core algorithm implementation',
                'Database connection/queries',
                'API endpoint handlers',
                'Key business logic functions',
            ];
        } elseif ($projectType === 'hardware') {
            $code['language'] = 'c/cpp/arduino';
            $code['snippets'] = [
                'Main program loop',
                'Sensor reading functions',
                'Output control functions',
                'Communication protocols',
            ];
        }

        return $code;
    }

    /**
     * Determine what content needs placeholders (can't be AI-generated)
     */
    private function determinePlaceholderNeeds(
        string $chapterType,
        string $projectType,
        array $diagrams
    ): array {
        $placeholders = [];

        // Filter diagrams that need placeholders
        foreach ($diagrams as $diagram) {
            if (! ($diagram['can_generate'] ?? false)) {
                $placeholders[] = [
                    'type' => $diagram['type'],
                    'description' => $diagram['description'],
                    'tool' => $diagram['tool'] ?? null,
                    'instructions_required' => true,
                ];
            }
        }

        // Screenshots are always placeholders
        if ($projectType === 'software' && in_array($chapterType, ['methodology', 'results'])) {
            $placeholders[] = [
                'type' => 'screenshot',
                'description' => 'Application screenshots showing user interface',
                'tool' => 'Screen capture tool',
                'instructions_required' => true,
            ];
        }

        return $placeholders;
    }

    /**
     * Get mock data configuration for tables
     */
    private function getMockDataConfig(array $tables): array
    {
        $mockDataTables = array_filter($tables, fn ($t) => $t['mock_data'] ?? false);

        return [
            'tables_needing_mock_data' => count($mockDataTables),
            'types' => array_column($mockDataTables, 'type'),
        ];
    }

    /**
     * Get citation requirements by chapter type
     */
    private function getCitationRequirements(string $chapterType, string $faculty): array
    {
        $minCitations = match ($chapterType) {
            'introduction' => ['min' => 10, 'max' => 20],
            'literature_review' => match ($faculty) {
                'science' => ['min' => 60, 'max' => 80],
                'engineering' => ['min' => 50, 'max' => 70],
                'social_science' => ['min' => 70, 'max' => 90],
                'healthcare' => ['min' => 50, 'max' => 70],
                'business' => ['min' => 60, 'max' => 80],
                default => ['min' => 50, 'max' => 70],
            },
            'methodology' => ['min' => 15, 'max' => 25],
            'results' => ['min' => 10, 'max' => 20],
            'discussion' => ['min' => 20, 'max' => 35],
            'conclusion' => ['min' => 5, 'max' => 15],
            default => ['min' => 10, 'max' => 20],
        };

        return [
            'min_citations' => $minCitations['min'],
            'max_citations' => $minCitations['max'],
            'format' => 'APA',
            'recency' => 'Last 5-7 years preferred',
        ];
    }

    /**
     * Get formatting rules by faculty
     */
    private function getFormattingRules(string $faculty): array
    {
        return [
            'citation_style' => 'APA',
            'third_person' => true,
            'no_ampersand' => true,
            'bullet_style' => '•',
            'section_numbering' => true,
            'table_format' => 'APA',
        ];
    }

    /**
     * Detect chapter type from number
     */
    private function detectChapterType(int $chapterNumber): string
    {
        return match ($chapterNumber) {
            1 => 'introduction',
            2 => 'literature_review',
            3 => 'methodology',
            4 => 'results',
            5 => 'discussion',
            default => 'general',
        };
    }

    /**
     * Convert chapter type to number
     */
    private function chapterTypeToNumber(string $chapterType): int
    {
        return match ($chapterType) {
            'introduction' => 1,
            'literature_review' => 2,
            'methodology' => 3,
            'results' => 4,
            'discussion', 'conclusion' => 5,
            default => 1,
        };
    }

    /**
     * Merge two requirement arrays, avoiding duplicates
     */
    private function mergeRequirements(array $base, array $additional): array
    {
        $merged = $base;

        foreach ($additional as $item) {
            $exists = false;
            foreach ($merged as $existing) {
                if (($existing['type'] ?? '') === ($item['type'] ?? '')) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $merged[] = $item;
            }
        }

        return $merged;
    }
}
Now I need to create the TableRequirements, DiagramRequirements, and ToolRecommendations classes in the Requirements directory:
Now let me create the Requirements classes:
Write TableRequirements.php
400 lines
<?php

namespace App\Services\PromptSystem\Requirements;

class TableRequirements
{
    /**
     * Get table requirements based on chapter type, project type, and faculty
     */
    public function getRequirements(string $chapterType, string $projectType, string $faculty): array
    {
        // Get faculty-specific requirements first
        $facultyRequirements = $this->getFacultyRequirements($chapterType, $faculty);

        // Get project-type specific requirements
        $projectRequirements = $this->getProjectTypeRequirements($chapterType, $projectType);

        // Merge with faculty taking precedence for conflicts
        return array_merge($projectRequirements, $facultyRequirements);
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
Now let me create the DiagramRequirements class:
Write DiagramRequirements.php
304 lines
<?php

namespace App\Services\PromptSystem\Requirements;

class DiagramRequirements
{
    /**
     * Get diagram requirements based on chapter type, project type, and faculty
     */
    public function getRequirements(string $chapterType, string $projectType, string $faculty): array
    {
        // Get faculty-specific requirements first
        $facultyRequirements = $this->getFacultyRequirements($chapterType, $faculty);

        // Get project-type specific requirements
        $projectRequirements = $this->getProjectTypeRequirements($chapterType, $projectType);

        return array_merge($projectRequirements, $facultyRequirements);
    }

    /**
     * Get faculty-specific diagram requirements
     */
    private function getFacultyRequirements(string $chapterType, string $faculty): array
    {
        $requirements = [
            'engineering' => [
                'methodology' => [
                    [
                        'type' => 'block_diagram',
                        'required' => true,
                        'can_generate' => true,
                        'description' => 'System block diagram showing major components and their relationships',
                        'format' => "graph LR\n    A[Input] --> B[Processing]\n    B --> C[Output]",
                        'tool' => null,
                    ],
                    [
                        'type' => 'circuit_diagram',
                        'required' => true,
                        'can_generate' => false, // Must be created by user
                        'description' => 'Complete circuit schematic with all components',
                        'tool' => 'Fritzing, EasyEDA, or Proteus',
                        'components' => ['microcontroller', 'sensors', 'actuators', 'power supply'],
                    ],
                    [
                        'type' => 'flowchart',
                        'required' => true,
                        'can_generate' => true,
                        'description' => 'System operation flowchart',
                        'format' => "flowchart TD\n    Start([Start]) --> Init[Initialize]\n    Init --> Read[Read Sensors]\n    Read --> Process[Process Data]\n    Process --> Decision{Condition?}\n    Decision -->|Yes| Action1[Action 1]\n    Decision -->|No| Action2[Action 2]\n    Action1 --> End([End])\n    Action2 --> End",
                    ],
                ],
                'results' => [
                    [
                        'type' => 'screenshot',
                        'required' => false,
                        'can_generate' => false,
                        'description' => 'Photos of completed hardware/prototype',
                        'tool' => 'Camera/Phone',
                    ],
                    [
                        'type' => 'performance_graph',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Graph showing performance metrics',
                        'format' => 'Description of graph to generate',
                    ],
                ],
            ],
            'social_science' => [
                'literature_review' => [
                    [
                        'type' => 'conceptual_framework',
                        'required' => true,
                        'can_generate' => true,
                        'description' => 'Conceptual framework showing variable relationships',
                        'format' => "graph LR\n    IV1[Independent Variable 1] --> DV[Dependent Variable]\n    IV2[Independent Variable 2] --> DV\n    MV[Moderating Variable] -.-> DV",
                    ],
                ],
                'methodology' => [
                    [
                        'type' => 'research_design_diagram',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Visual representation of research design',
                        'format' => "flowchart TD\n    Pop[Population] --> Sample[Sample Selection]\n    Sample --> Data[Data Collection]\n    Data --> Analysis[Data Analysis]\n    Analysis --> Results[Results]",
                    ],
                ],
            ],
            'healthcare' => [
                'methodology' => [
                    [
                        'type' => 'care_pathway',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Patient care pathway diagram',
                        'format' => "flowchart TD\n    Assess[Assessment] --> Diag[Diagnosis]\n    Diag --> Plan[Care Planning]\n    Plan --> Impl[Implementation]\n    Impl --> Eval[Evaluation]\n    Eval -->|Improved| Discharge[Discharge]\n    Eval -->|Not Improved| Plan",
                    ],
                    [
                        'type' => 'study_flow',
                        'required' => true,
                        'can_generate' => true,
                        'description' => 'CONSORT-style participant flow diagram',
                        'format' => "flowchart TD\n    Assessed[Assessed for Eligibility n=X] --> Excluded[Excluded n=X]\n    Assessed --> Enrolled[Enrolled n=X]\n    Enrolled --> Intervention[Intervention Group n=X]\n    Enrolled --> Control[Control Group n=X]\n    Intervention --> FollowUp1[Follow-up n=X]\n    Control --> FollowUp2[Follow-up n=X]\n    FollowUp1 --> Analysis1[Analyzed n=X]\n    FollowUp2 --> Analysis2[Analyzed n=X]",
                    ],
                ],
            ],
            'business' => [
                'literature_review' => [
                    [
                        'type' => 'theoretical_model',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Theoretical framework diagram',
                        'format' => "graph TB\n    Theory[Theory] --> H1[Hypothesis 1]\n    Theory --> H2[Hypothesis 2]\n    H1 --> DV[Dependent Variable]\n    H2 --> DV",
                    ],
                ],
                'results' => [
                    [
                        'type' => 'organizational_chart',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Organizational structure diagram',
                        'format' => "graph TD\n    CEO[CEO] --> CFO[CFO]\n    CEO --> COO[COO]\n    CEO --> CTO[CTO]\n    CFO --> Finance[Finance Team]\n    COO --> Operations[Operations Team]\n    CTO --> Tech[Tech Team]",
                    ],
                ],
            ],
            'science' => [
                'methodology' => [
                    [
                        'type' => 'experimental_setup',
                        'required' => false,
                        'can_generate' => false,
                        'description' => 'Diagram of experimental setup',
                        'tool' => 'Draw.io or ChemDraw',
                    ],
                    [
                        'type' => 'procedure_flowchart',
                        'required' => true,
                        'can_generate' => true,
                        'description' => 'Experimental procedure flowchart',
                        'format' => "flowchart TD\n    Prep[Preparation] --> Setup[Setup Equipment]\n    Setup --> Calibrate[Calibration]\n    Calibrate --> Experiment[Run Experiment]\n    Experiment --> Record[Record Data]\n    Record --> Repeat{Repeat?}\n    Repeat -->|Yes| Experiment\n    Repeat -->|No| Analyze[Analyze Results]",
                    ],
                ],
            ],
        ];

        return $requirements[$faculty][$chapterType] ?? [];
    }

    /**
     * Get project-type specific diagram requirements
     */
    private function getProjectTypeRequirements(string $chapterType, string $projectType): array
    {
        $requirements = [
            'software' => [
                'methodology' => [
                    [
                        'type' => 'system_architecture',
                        'required' => true,
                        'can_generate' => true,
                        'description' => 'System architecture diagram showing all components',
                        'format' => "graph TB\n    subgraph Frontend\n        UI[User Interface]\n    end\n    subgraph Backend\n        API[API Server]\n        Auth[Authentication]\n    end\n    subgraph Database\n        DB[(Database)]\n    end\n    UI --> API\n    API --> Auth\n    API --> DB",
                    ],
                    [
                        'type' => 'use_case_diagram',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Use case diagram showing user interactions',
                        'format' => "graph LR\n    User((User)) --> Login[Login]\n    User --> Register[Register]\n    User --> ViewData[View Data]\n    Admin((Admin)) --> ManageUsers[Manage Users]\n    Admin --> ViewReports[View Reports]",
                    ],
                    [
                        'type' => 'erd',
                        'required' => true,
                        'can_generate' => true,
                        'description' => 'Entity Relationship Diagram for database',
                        'format' => "erDiagram\n    USER ||--o{ ORDER : places\n    ORDER ||--|{ ORDER_ITEM : contains\n    PRODUCT ||--o{ ORDER_ITEM : includes\n    USER {\n        int id PK\n        string name\n        string email\n    }\n    ORDER {\n        int id PK\n        int user_id FK\n        date created_at\n    }",
                    ],
                    [
                        'type' => 'sequence_diagram',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Sequence diagram for key processes',
                        'format' => "sequenceDiagram\n    participant U as User\n    participant F as Frontend\n    participant B as Backend\n    participant D as Database\n    U->>F: Submit Form\n    F->>B: POST /api/data\n    B->>D: INSERT data\n    D-->>B: Success\n    B-->>F: 200 OK\n    F-->>U: Show Success",
                    ],
                ],
                'results' => [
                    [
                        'type' => 'screenshot',
                        'required' => true,
                        'can_generate' => false,
                        'description' => 'Screenshots of application interfaces',
                        'tool' => 'Screen capture tool (e.g., Snipping Tool, Lightshot)',
                    ],
                ],
            ],
            'hardware' => [
                'methodology' => [
                    [
                        'type' => 'circuit_schematic',
                        'required' => true,
                        'can_generate' => false,
                        'description' => 'Complete circuit schematic diagram',
                        'tool' => 'Fritzing, EasyEDA, Proteus, or KiCad',
                        'components' => ['microcontroller', 'sensors', 'actuators', 'power_supply', 'communication_modules'],
                    ],
                    [
                        'type' => 'pcb_layout',
                        'required' => false,
                        'can_generate' => false,
                        'description' => 'PCB layout design',
                        'tool' => 'EasyEDA, KiCad, or Eagle',
                    ],
                    [
                        'type' => 'wiring_diagram',
                        'required' => false,
                        'can_generate' => false,
                        'description' => 'Wiring connection diagram',
                        'tool' => 'Fritzing (breadboard view)',
                    ],
                ],
                'results' => [
                    [
                        'type' => 'hardware_photo',
                        'required' => true,
                        'can_generate' => false,
                        'description' => 'Photos of completed hardware prototype',
                        'tool' => 'Camera/Smartphone',
                    ],
                    [
                        'type' => 'oscilloscope_capture',
                        'required' => false,
                        'can_generate' => false,
                        'description' => 'Oscilloscope waveform captures',
                        'tool' => 'Oscilloscope with USB/Screenshot capability',
                    ],
                ],
            ],
            'survey_research' => [
                'results' => [
                    [
                        'type' => 'bar_chart',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Bar chart for categorical data',
                        'format' => 'Generate using SPSS or Excel',
                    ],
                    [
                        'type' => 'pie_chart',
                        'required' => false,
                        'can_generate' => true,
                        'description' => 'Pie chart for proportional data',
                        'format' => 'Generate using SPSS or Excel',
                    ],
                ],
            ],
        ];

        return $requirements[$projectType][$chapterType] ?? [];
    }

    /**
     * Check if a diagram type can be AI-generated (using Mermaid)
     */
    public function canGenerate(string $diagramType): bool
    {
        $generatableDiagrams = [
            'flowchart',
            'block_diagram',
            'system_architecture',
            'use_case_diagram',
            'erd',
            'sequence_diagram',
            'conceptual_framework',
            'theoretical_model',
            'care_pathway',
            'study_flow',
            'procedure_flowchart',
            'organizational_chart',
            'research_design_diagram',
        ];

        return in_array($diagramType, $generatableDiagrams);
    }

    /**
     * Get all diagram types that need user creation
     */
    public function getPlaceholderDiagrams(): array
    {
        return [
            'circuit_diagram',
            'circuit_schematic',
            'pcb_layout',
            'wiring_diagram',
            'screenshot',
            'hardware_photo',
            'oscilloscope_capture',
            'experimental_setup',
        ];
    }
}
Now let me create the ToolRecommendations class and then the MockDataGenerator and PlaceholderInstructionBuilder. After that, I'll create the Faculty templates.
Write ToolRecommendations.php
432 lines
<?php

namespace App\Services\PromptSystem\Requirements;

class ToolRecommendations
{
    /**
     * Get tool recommendations for a given context
     */
    public function getToolsForContext(string $projectType, string $faculty, string $chapterType): array
    {
        $tools = [];

        // Get faculty-specific tools
        $facultyTools = $this->getFacultyTools($faculty);
        $tools = array_merge($tools, $facultyTools);

        // Get project-type specific tools
        $projectTools = $this->getProjectTypeTools($projectType);
        $tools = array_merge($tools, $projectTools);

        return $tools;
    }

    /**
     * Get tools by faculty
     */
    private function getFacultyTools(string $faculty): array
    {
        $tools = [
            'engineering' => [
                'circuit_design' => [
                    [
                        'name' => 'Fritzing',
                        'url' => 'fritzing.org',
                        'cost' => 'Free',
                        'best_for' => 'Arduino projects, breadboard prototypes, beginner-friendly',
                    ],
                    [
                        'name' => 'EasyEDA',
                        'url' => 'easyeda.com',
                        'cost' => 'Free',
                        'best_for' => 'PCB design, professional schematics, component library',
                    ],
                    [
                        'name' => 'Proteus',
                        'url' => 'labcenter.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Circuit simulation, microcontroller simulation, debugging',
                    ],
                    [
                        'name' => 'KiCad',
                        'url' => 'kicad.org',
                        'cost' => 'Free (Open Source)',
                        'best_for' => 'Professional PCB design, industry standard',
                    ],
                    [
                        'name' => 'LTspice',
                        'url' => 'analog.com',
                        'cost' => 'Free',
                        'best_for' => 'Circuit simulation, SPICE analysis',
                    ],
                ],
                'programming' => [
                    [
                        'name' => 'Arduino IDE',
                        'url' => 'arduino.cc/en/software',
                        'cost' => 'Free',
                        'best_for' => 'Arduino programming, serial monitor',
                    ],
                    [
                        'name' => 'PlatformIO',
                        'url' => 'platformio.org',
                        'cost' => 'Free',
                        'best_for' => 'Multi-platform embedded development, VS Code integration',
                    ],
                    [
                        'name' => 'MPLAB X',
                        'url' => 'microchip.com',
                        'cost' => 'Free',
                        'best_for' => 'PIC microcontroller programming',
                    ],
                ],
                'simulation' => [
                    [
                        'name' => 'MATLAB/Simulink',
                        'url' => 'mathworks.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Control systems, signal processing, system modeling',
                    ],
                    [
                        'name' => 'Multisim',
                        'url' => 'ni.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Circuit simulation, educational',
                    ],
                ],
            ],
            'social_science' => [
                'statistics' => [
                    [
                        'name' => 'IBM SPSS Statistics',
                        'url' => 'ibm.com/spss',
                        'cost' => 'Academic License',
                        'best_for' => 'Survey analysis, hypothesis testing, regression',
                    ],
                    [
                        'name' => 'R/RStudio',
                        'url' => 'rstudio.com',
                        'cost' => 'Free (Open Source)',
                        'best_for' => 'Advanced statistics, custom analysis, reproducible research',
                    ],
                    [
                        'name' => 'JASP',
                        'url' => 'jasp-stats.org',
                        'cost' => 'Free',
                        'best_for' => 'Beginner-friendly SPSS alternative, Bayesian statistics',
                    ],
                    [
                        'name' => 'Jamovi',
                        'url' => 'jamovi.org',
                        'cost' => 'Free',
                        'best_for' => 'User-friendly statistics, R integration',
                    ],
                ],
                'survey' => [
                    [
                        'name' => 'Google Forms',
                        'url' => 'forms.google.com',
                        'cost' => 'Free',
                        'best_for' => 'Quick online surveys, automatic data collection',
                    ],
                    [
                        'name' => 'SurveyMonkey',
                        'url' => 'surveymonkey.com',
                        'cost' => 'Free tier / Paid',
                        'best_for' => 'Professional surveys, advanced question types',
                    ],
                    [
                        'name' => 'Kobo Toolbox',
                        'url' => 'kobotoolbox.org',
                        'cost' => 'Free',
                        'best_for' => 'Field data collection, offline capability, humanitarian research',
                    ],
                    [
                        'name' => 'Qualtrics',
                        'url' => 'qualtrics.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Advanced survey logic, academic research',
                    ],
                ],
                'qualitative' => [
                    [
                        'name' => 'NVivo',
                        'url' => 'qsrinternational.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Qualitative data analysis, thematic coding',
                    ],
                    [
                        'name' => 'ATLAS.ti',
                        'url' => 'atlasti.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Mixed methods, text analysis',
                    ],
                    [
                        'name' => 'MAXQDA',
                        'url' => 'maxqda.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Qualitative and mixed methods analysis',
                    ],
                ],
            ],
            'healthcare' => [
                'statistics' => [
                    [
                        'name' => 'IBM SPSS Statistics',
                        'url' => 'ibm.com/spss',
                        'cost' => 'Academic License',
                        'best_for' => 'Clinical data analysis, common in nursing research',
                    ],
                    [
                        'name' => 'Epi Info',
                        'url' => 'cdc.gov/epiinfo',
                        'cost' => 'Free',
                        'best_for' => 'Epidemiological analysis, public health research',
                    ],
                    [
                        'name' => 'R/RStudio',
                        'url' => 'rstudio.com',
                        'cost' => 'Free (Open Source)',
                        'best_for' => 'Biostatistics, survival analysis, advanced methods',
                    ],
                    [
                        'name' => 'GraphPad Prism',
                        'url' => 'graphpad.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Biomedical statistics, publication-ready graphs',
                    ],
                ],
                'data_collection' => [
                    [
                        'name' => 'REDCap',
                        'url' => 'project-redcap.org',
                        'cost' => 'Free (Institutional)',
                        'best_for' => 'Clinical data capture, HIPAA compliant',
                    ],
                    [
                        'name' => 'ODK (Open Data Kit)',
                        'url' => 'getodk.org',
                        'cost' => 'Free',
                        'best_for' => 'Mobile health data collection, offline capability',
                    ],
                    [
                        'name' => 'Kobo Toolbox',
                        'url' => 'kobotoolbox.org',
                        'cost' => 'Free',
                        'best_for' => 'Community health surveys, field research',
                    ],
                ],
            ],
            'business' => [
                'analysis' => [
                    [
                        'name' => 'Microsoft Excel',
                        'url' => 'microsoft.com/excel',
                        'cost' => 'Paid / Academic License',
                        'best_for' => 'Financial modeling, data analysis, projections',
                    ],
                    [
                        'name' => 'Google Sheets',
                        'url' => 'sheets.google.com',
                        'cost' => 'Free',
                        'best_for' => 'Collaborative analysis, basic statistics',
                    ],
                    [
                        'name' => 'IBM SPSS Statistics',
                        'url' => 'ibm.com/spss',
                        'cost' => 'Academic License',
                        'best_for' => 'Market research analysis, survey data',
                    ],
                    [
                        'name' => 'Tableau',
                        'url' => 'tableau.com',
                        'cost' => 'Free (Public) / Paid',
                        'best_for' => 'Data visualization, dashboards',
                    ],
                ],
                'financial' => [
                    [
                        'name' => 'Microsoft Excel',
                        'url' => 'microsoft.com/excel',
                        'cost' => 'Paid / Academic License',
                        'best_for' => 'Financial statements, NPV/IRR calculations, budgeting',
                    ],
                    [
                        'name' => 'QuickBooks',
                        'url' => 'quickbooks.com',
                        'cost' => 'Paid',
                        'best_for' => 'Accounting, financial reports',
                    ],
                ],
            ],
            'science' => [
                'analysis' => [
                    [
                        'name' => 'Origin Pro',
                        'url' => 'originlab.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Scientific graphing, data analysis',
                    ],
                    [
                        'name' => 'GraphPad Prism',
                        'url' => 'graphpad.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Scientific statistics, publication-ready graphs',
                    ],
                    [
                        'name' => 'R/RStudio',
                        'url' => 'rstudio.com',
                        'cost' => 'Free (Open Source)',
                        'best_for' => 'Custom analysis, reproducible research',
                    ],
                    [
                        'name' => 'Python (SciPy/NumPy)',
                        'url' => 'scipy.org',
                        'cost' => 'Free (Open Source)',
                        'best_for' => 'Computational science, automation',
                    ],
                ],
                'molecular' => [
                    [
                        'name' => 'ChemDraw',
                        'url' => 'perkinelmer.com/chemdraw',
                        'cost' => 'Academic License',
                        'best_for' => 'Chemical structures, reaction schemes',
                    ],
                    [
                        'name' => 'Avogadro',
                        'url' => 'avogadro.cc',
                        'cost' => 'Free (Open Source)',
                        'best_for' => '3D molecular modeling',
                    ],
                ],
            ],
        ];

        return $tools[$faculty] ?? [];
    }

    /**
     * Get project-type specific tools
     */
    private function getProjectTypeTools(string $projectType): array
    {
        $tools = [
            'software' => [
                'development' => [
                    [
                        'name' => 'Visual Studio Code',
                        'url' => 'code.visualstudio.com',
                        'cost' => 'Free',
                        'best_for' => 'Code editing, debugging, extensions',
                    ],
                    [
                        'name' => 'Git/GitHub',
                        'url' => 'github.com',
                        'cost' => 'Free',
                        'best_for' => 'Version control, collaboration',
                    ],
                    [
                        'name' => 'Postman',
                        'url' => 'postman.com',
                        'cost' => 'Free tier',
                        'best_for' => 'API testing and documentation',
                    ],
                ],
                'design' => [
                    [
                        'name' => 'Figma',
                        'url' => 'figma.com',
                        'cost' => 'Free tier',
                        'best_for' => 'UI/UX design, prototyping',
                    ],
                    [
                        'name' => 'Draw.io',
                        'url' => 'draw.io',
                        'cost' => 'Free',
                        'best_for' => 'Flowcharts, system diagrams, UML',
                    ],
                ],
            ],
            'hardware' => [
                'circuit_design' => [
                    [
                        'name' => 'Fritzing',
                        'url' => 'fritzing.org',
                        'cost' => 'Free',
                        'best_for' => 'Arduino projects, breadboard designs',
                    ],
                    [
                        'name' => 'EasyEDA',
                        'url' => 'easyeda.com',
                        'cost' => 'Free',
                        'best_for' => 'PCB design, component sourcing',
                    ],
                    [
                        'name' => 'Proteus',
                        'url' => 'labcenter.com',
                        'cost' => 'Academic License',
                        'best_for' => 'Simulation, microcontroller debugging',
                    ],
                ],
            ],
            'survey_research' => [
                'survey' => [
                    [
                        'name' => 'Google Forms',
                        'url' => 'forms.google.com',
                        'cost' => 'Free',
                        'best_for' => 'Quick surveys',
                    ],
                    [
                        'name' => 'Kobo Toolbox',
                        'url' => 'kobotoolbox.org',
                        'cost' => 'Free',
                        'best_for' => 'Field data collection',
                    ],
                ],
                'statistics' => [
                    [
                        'name' => 'SPSS',
                        'url' => 'ibm.com/spss',
                        'cost' => 'Academic License',
                        'best_for' => 'Survey analysis',
                    ],
                ],
            ],
        ];

        return $tools[$projectType] ?? [];
    }

    /**
     * Get tools for a specific diagram type
     */
    public function getToolsForDiagram(string $diagramType): array
    {
        $diagramTools = [
            'circuit_diagram' => [
                ['name' => 'Fritzing', 'url' => 'fritzing.org', 'cost' => 'Free'],
                ['name' => 'EasyEDA', 'url' => 'easyeda.com', 'cost' => 'Free'],
                ['name' => 'Proteus', 'url' => 'labcenter.com', 'cost' => 'Academic'],
            ],
            'flowchart' => [
                ['name' => 'Draw.io', 'url' => 'draw.io', 'cost' => 'Free'],
                ['name' => 'Lucidchart', 'url' => 'lucidchart.com', 'cost' => 'Free tier'],
            ],
            'uml' => [
                ['name' => 'Draw.io', 'url' => 'draw.io', 'cost' => 'Free'],
                ['name' => 'StarUML', 'url' => 'staruml.io', 'cost' => 'Free trial'],
            ],
            'screenshot' => [
                ['name' => 'Snipping Tool', 'url' => 'Built into Windows', 'cost' => 'Free'],
                ['name' => 'Lightshot', 'url' => 'app.prntscr.com', 'cost' => 'Free'],
                ['name' => 'ShareX', 'url' => 'getsharex.com', 'cost' => 'Free'],
            ],
        ];

        return $diagramTools[$diagramType] ?? [];
    }
}
This session is being continued from a previous conversation that ran out of context. The conversation is summarized below:
Limit reached · resets 2am (Africa/Lagos) · /upgrade to Max or turn on /extra-usage.
Please continue the conversation from where we left it off without asking the user any further questions. Continue with the last task that you were asked to work on.