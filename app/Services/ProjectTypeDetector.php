<?php

namespace App\Services;

use App\Models\Project;

/**
 * Detects project type and provides context-aware content generation guidance
 */
class ProjectTypeDetector
{
    /**
     * Project type categories
     */
    public const TYPE_SOFTWARE = 'software';

    public const TYPE_HARDWARE = 'hardware';

    public const TYPE_RESEARCH = 'research';

    public const TYPE_DESIGN = 'design';

    public const TYPE_BUSINESS = 'business';

    public const TYPE_SCIENCE = 'science';

    public const TYPE_HEALTHCARE = 'healthcare';

    public const TYPE_GENERAL = 'general';

    /**
     * Detect the primary project type based on faculty, course, and topic
     */
    public function detectProjectType(Project $project): string
    {
        $topic = strtolower($project->topic ?? '');
        $course = strtolower($project->course ?? '');
        $faculty = strtolower($project->faculty ?? '');

        // PRIORITY 1: Check faculty/course for very specific domains (highest confidence)
        // Nursing/Healthcare
        if (str_contains($faculty, 'nursing') || str_contains($course, 'nursing') ||
            str_contains($faculty, 'health') || str_contains($faculty, 'medicine') ||
            str_contains($faculty, 'medical')) {
            return self::TYPE_HEALTHCARE;
        }

        // Business/Management
        if (str_contains($faculty, 'business') || str_contains($faculty, 'management') ||
            str_contains($faculty, 'economics') || str_contains($course, 'business')) {
            return self::TYPE_BUSINESS;
        }

        // Pure Science
        if (str_contains($faculty, 'science') && ! str_contains($course, 'computer')) {
            return self::TYPE_SCIENCE;
        }

        // PRIORITY 2: Keyword matching with weighted scoring
        // Healthcare/Nursing keywords (added)
        $healthcareKeywords = [
            'nursing', 'patient', 'clinical', 'health', 'medical', 'hospital',
            'care', 'treatment', 'diagnosis', 'malnutrition', 'nutrition',
            'disease', 'intervention', 'therapy', 'healthcare', 'medicine',
        ];

        // Software/IT project keywords (more specific - removed generic words)
        $softwareKeywords = [
            'blockchain', 'database', 'api', 'algorithm', 'programming',
            'machine learning', 'ai', 'artificial intelligence', 'neural network',
            'cloud computing', 'cybersecurity', 'mobile app', 'iot',
        ];

        // Hardware/Electronics keywords
        $hardwareKeywords = [
            'circuit', 'hardware', 'embedded', 'microcontroller', 'arduino',
            'raspberry pi', 'sensor', 'actuator', 'pcb', 'electronic',
            'robotics', 'automation', 'plc', 'scada', 'motor control',
        ];

        // Research/Scientific keywords
        $researchKeywords = [
            'study', 'investigation', 'evaluation', 'assessment',
            'impact', 'effect', 'relationship', 'correlation', 'survey',
            'experimental', 'empirical', 'comparative', 'analysis',
        ];

        // Design/Engineering keywords
        $designKeywords = [
            'cad', 'modeling', 'simulation', 'optimization',
            'prototype', 'fabrication', 'manufacturing', 'construction',
        ];

        // Business/Management keywords
        $businessKeywords = [
            'marketing', 'finance', 'accounting', 'strategic',
            'entrepreneurship', 'organizational', 'hr',
        ];

        // Science/Laboratory keywords
        $scienceKeywords = [
            'chemical', 'biological', 'physics', 'chemistry', 'biology',
            'laboratory', 'experiment', 'specimen', 'synthesis', 'reaction',
        ];

        // Count matches for each category (topic + course combined)
        $scores = [
            self::TYPE_HEALTHCARE => $this->countKeywordMatches($topic.' '.$course, $healthcareKeywords),
            self::TYPE_SOFTWARE => $this->countKeywordMatches($topic.' '.$course, $softwareKeywords),
            self::TYPE_HARDWARE => $this->countKeywordMatches($topic.' '.$course, $hardwareKeywords),
            self::TYPE_RESEARCH => $this->countKeywordMatches($topic.' '.$course, $researchKeywords),
            self::TYPE_DESIGN => $this->countKeywordMatches($topic.' '.$course, $designKeywords),
            self::TYPE_BUSINESS => $this->countKeywordMatches($topic.' '.$course, $businessKeywords),
            self::TYPE_SCIENCE => $this->countKeywordMatches($topic.' '.$course, $scienceKeywords),
        ];

        // Get type with highest score
        arsort($scores);
        $primaryType = array_key_first($scores);

        // If no clear match, use faculty as fallback
        if ($scores[$primaryType] === 0) {
            if (str_contains($faculty, 'engineering') || str_contains($faculty, 'technology')) {
                return str_contains($course, 'computer') || str_contains($course, 'software')
                    ? self::TYPE_SOFTWARE
                    : self::TYPE_HARDWARE;
            }

            return self::TYPE_RESEARCH; // Default to research for academic projects
        }

        return $primaryType;
    }

    /**
     * Get all applicable project types (can have multiple)
     */
    public function detectProjectTypes(Project $project): array
    {
        $topic = strtolower($project->topic ?? '');
        $course = strtolower($project->course ?? '');

        $types = [];

        // Check for software
        if ($this->containsAny($topic.$course, ['software', 'system', 'application', 'blockchain', 'web', 'mobile'])) {
            $types[] = self::TYPE_SOFTWARE;
        }

        // Check for hardware
        if ($this->containsAny($topic.$course, ['hardware', 'circuit', 'embedded', 'arduino', 'sensor'])) {
            $types[] = self::TYPE_HARDWARE;
        }

        // Check for design
        if ($this->containsAny($topic.$course, ['design', 'modeling', 'simulation', 'prototype'])) {
            $types[] = self::TYPE_DESIGN;
        }

        return empty($types) ? [self::TYPE_GENERAL] : $types;
    }

    /**
     * Get content generation capabilities based on project type
     */
    public function getContentCapabilities(string $projectType): array
    {
        return match ($projectType) {
            self::TYPE_SOFTWARE => [
                'can_generate_code' => true,
                'can_generate_diagrams' => true, // Mermaid diagrams
                'can_generate_calculations' => true,
                'can_generate_data' => true,
                'can_generate_tables' => true,
                'needs_screenshots' => true,
                'needs_circuit_diagrams' => false,
                'diagram_types' => ['flowchart', 'sequence', 'class', 'erd', 'architecture'],
            ],
            self::TYPE_HARDWARE => [
                'can_generate_code' => true, // Arduino/embedded code
                'can_generate_diagrams' => false, // Circuit diagrams need tools
                'can_generate_calculations' => true,
                'can_generate_data' => true,
                'can_generate_tables' => true,
                'needs_screenshots' => false,
                'needs_circuit_diagrams' => true,
                'diagram_types' => [],
            ],
            self::TYPE_RESEARCH => [
                'can_generate_code' => false,
                'can_generate_diagrams' => true, // Research models
                'can_generate_calculations' => true, // Statistics
                'can_generate_data' => true, // Sample data
                'can_generate_tables' => true,
                'needs_screenshots' => false,
                'needs_circuit_diagrams' => false,
                'diagram_types' => ['flowchart', 'conceptual_model'],
            ],
            self::TYPE_BUSINESS => [
                'can_generate_code' => false,
                'can_generate_diagrams' => true,
                'can_generate_calculations' => true, // Financial calculations
                'can_generate_data' => true, // Financial projections
                'can_generate_tables' => true,
                'needs_screenshots' => false,
                'needs_circuit_diagrams' => false,
                'diagram_types' => ['flowchart', 'organizational', 'swot'],
            ],
            self::TYPE_SCIENCE => [
                'can_generate_code' => false,
                'can_generate_diagrams' => true,
                'can_generate_calculations' => true,
                'can_generate_data' => true, // Experimental data
                'can_generate_tables' => true,
                'needs_screenshots' => false,
                'needs_circuit_diagrams' => false,
                'diagram_types' => ['flowchart'],
            ],
            self::TYPE_HEALTHCARE => [
                'can_generate_code' => false,
                'can_generate_diagrams' => true, // Care pathways, frameworks
                'can_generate_calculations' => true, // BMI, dosage, statistics
                'can_generate_data' => true, // Patient data, clinical trials
                'can_generate_tables' => true, // Assessment tools, patient records
                'needs_screenshots' => false,
                'needs_circuit_diagrams' => false,
                'diagram_types' => ['flowchart', 'care_pathway'],
            ],
            default => [
                'can_generate_code' => false,
                'can_generate_diagrams' => true,
                'can_generate_calculations' => true,
                'can_generate_data' => true,
                'can_generate_tables' => true,
                'needs_screenshots' => false,
                'needs_circuit_diagrams' => false,
                'diagram_types' => ['flowchart'],
            ],
        };
    }

    /**
     * Get context-aware instructions for AI based on project type
     */
    public function getContextualInstructions(Project $project, int $chapterNumber): string
    {
        $projectType = $this->detectProjectType($project);
        $capabilities = $this->getContentCapabilities($projectType);

        $instructions = "\n\n📊 CONTEXT-AWARE CONTENT GENERATION:\n";
        $instructions .= 'Project Type Detected: '.strtoupper($projectType)."\n\n";

        // Add type-specific instructions
        $instructions .= $this->getTypeSpecificInstructions($projectType, $chapterNumber, $capabilities);

        // Add diagram generation instructions
        if ($capabilities['can_generate_diagrams']) {
            $instructions .= $this->getDiagramInstructions($capabilities['diagram_types']);
        }

        // Add calculation instructions
        if ($capabilities['can_generate_calculations']) {
            $instructions .= $this->getCalculationInstructions($projectType);
        }

        // Add code generation instructions
        if ($capabilities['can_generate_code']) {
            $instructions .= $this->getCodeGenerationInstructions($projectType);
        }

        // Add data generation instructions
        if ($capabilities['can_generate_data']) {
            $instructions .= $this->getDataGenerationInstructions($projectType);
        }

        // Add placeholder instructions
        $instructions .= $this->getPlaceholderInstructions($capabilities);

        return $instructions;
    }

    /**
     * Get type-specific generation instructions
     */
    private function getTypeSpecificInstructions(string $projectType, int $chapterNumber, array $capabilities): string
    {
        $instructions = "TYPE-SPECIFIC REQUIREMENTS:\n";

        switch ($projectType) {
            case self::TYPE_SOFTWARE:
                $instructions .= "✅ MUST INCLUDE:\n";
                $instructions .= "- Code snippets with inline comments explaining logic\n";
                $instructions .= "- System architecture diagrams (Mermaid format)\n";
                $instructions .= "- Database schemas (ER diagrams in Mermaid)\n";
                $instructions .= "- Flowcharts showing algorithms/processes\n";
                $instructions .= "- Sample API requests/responses\n";
                $instructions .= "- Sequence diagrams for key interactions\n";

                if ($chapterNumber === 3) { // Methodology
                    $instructions .= "\n🔧 CHAPTER 3 SPECIFICS:\n";
                    $instructions .= "- Include actual code for core algorithms\n";
                    $instructions .= "- Show database table structures with sample data\n";
                    $instructions .= "- Generate system architecture with all components\n";
                    $instructions .= "- Include UML class diagrams for key classes\n";
                }

                if ($chapterNumber === 4) { // Implementation/Results
                    $instructions .= "\n📊 CHAPTER 4 SPECIFICS:\n";
                    $instructions .= "- Show implementation code with full explanations\n";
                    $instructions .= "- Include test results with sample data\n";
                    $instructions .= "- Generate performance metrics tables\n";
                    $instructions .= "- Create placeholders for actual screenshots\n";
                }
                break;

            case self::TYPE_HARDWARE:
                $instructions .= "✅ MUST INCLUDE:\n";
                $instructions .= "- Component specifications tables\n";
                $instructions .= "- Pin connection tables\n";
                $instructions .= "- Sample Arduino/embedded code\n";
                $instructions .= "- Electrical calculations (voltage, current, resistance)\n";
                $instructions .= "- Power consumption analysis\n";

                $instructions .= "\n🖼️ MUST CREATE PLACEHOLDERS FOR:\n";
                $instructions .= "- Circuit diagrams (with detailed instructions)\n";
                $instructions .= "- PCB layouts\n";
                $instructions .= "- Wiring diagrams\n";
                $instructions .= "- Component photos\n";
                break;

            case self::TYPE_RESEARCH:
                $instructions .= "✅ MUST INCLUDE:\n";
                $instructions .= "- Statistical calculations with step-by-step workings\n";
                $instructions .= "- Sample survey data tables\n";
                $instructions .= "- Statistical test results (t-test, ANOVA, etc.)\n";
                $instructions .= "- Correlation/regression analysis with formulas\n";
                $instructions .= "- Conceptual framework diagrams\n";
                break;

            case self::TYPE_BUSINESS:
                $instructions .= "✅ MUST INCLUDE:\n";
                $instructions .= "- Financial calculations (ROI, NPV, IRR)\n";
                $instructions .= "- SWOT analysis diagrams\n";
                $instructions .= "- Organizational charts\n";
                $instructions .= "- Market analysis tables\n";
                $instructions .= "- Budget projections with formulas\n";
                break;

            case self::TYPE_SCIENCE:
                $instructions .= "✅ MUST INCLUDE:\n";
                $instructions .= "- Experimental data tables with units\n";
                $instructions .= "- Formula derivations step-by-step\n";
                $instructions .= "- Calculation examples with all steps shown\n";
                $instructions .= "- Statistical analysis of results\n";
                $instructions .= "- Sample experimental procedure flowchart\n";
                break;

            case self::TYPE_HEALTHCARE:
                $instructions .= "✅ MUST INCLUDE:\n";
                $instructions .= "- Patient assessment tools and scoring systems\n";
                $instructions .= "- Sample clinical data tables (anonymized)\n";
                $instructions .= "- Statistical analysis of health outcomes\n";
                $instructions .= "- Care pathway flowcharts\n";
                $instructions .= "- Intervention protocols and procedures\n";
                $instructions .= "- Health calculations (BMI, medication dosages, growth charts)\n";

                if ($chapterNumber === 3) { // Methodology
                    $instructions .= "\n🏥 CHAPTER 3 SPECIFICS:\n";
                    $instructions .= "- Include detailed intervention protocols\n";
                    $instructions .= "- Show assessment instruments/questionnaires\n";
                    $instructions .= "- Generate sample data collection forms\n";
                    $instructions .= "- Create care pathway diagrams\n";
                }

                if ($chapterNumber === 4) { // Results
                    $instructions .= "\n📊 CHAPTER 4 SPECIFICS:\n";
                    $instructions .= "- Present patient outcome data in tables\n";
                    $instructions .= "- Include statistical analysis (t-tests, chi-square)\n";
                    $instructions .= "- Show before/after intervention comparisons\n";
                    $instructions .= "- Generate health metrics charts\n";
                }
                break;
        }

        return $instructions."\n";
    }

    /**
     * Get Mermaid diagram generation instructions
     */
    private function getDiagramInstructions(array $diagramTypes): string
    {
        $instructions = "📐 DIAGRAM GENERATION INSTRUCTIONS:\n";
        $instructions .= "Use Mermaid syntax to generate diagrams directly in the text. Format:\n\n";
        $instructions .= "```mermaid\n";
        $instructions .= "graph TD\n";
        $instructions .= "    A[Start] --> B[Process]\n";
        $instructions .= "```\n\n";

        $instructions .= "AVAILABLE DIAGRAM TYPES:\n";

        if (in_array('flowchart', $diagramTypes)) {
            $instructions .= "• Flowcharts: Use 'graph TD' (top-down) or 'graph LR' (left-right)\n";
        }

        if (in_array('sequence', $diagramTypes)) {
            $instructions .= "• Sequence Diagrams: Use 'sequenceDiagram' for interaction flows\n";
        }

        if (in_array('class', $diagramTypes)) {
            $instructions .= "• Class Diagrams: Use 'classDiagram' for OOP structures\n";
        }

        if (in_array('erd', $diagramTypes)) {
            $instructions .= "• ERD: Use 'erDiagram' for database relationships\n";
        }

        $instructions .= "\n";

        return $instructions;
    }

    /**
     * Get calculation generation instructions
     */
    private function getCalculationInstructions(string $projectType): string
    {
        $instructions = "🧮 CALCULATION REQUIREMENTS:\n";
        $instructions .= "When showing calculations, ALWAYS:\n";
        $instructions .= "1. State the formula first\n";
        $instructions .= "2. Show step-by-step substitution\n";
        $instructions .= "3. Perform arithmetic operations\n";
        $instructions .= "4. Show final result with units\n";
        $instructions .= "5. Explain what the result means\n\n";

        $instructions .= "EXAMPLE FORMAT:\n";

        switch ($projectType) {
            case self::TYPE_SOFTWARE:
                $instructions .= "```\n";
                $instructions .= "Time Complexity Analysis:\n";
                $instructions .= "Formula: T(n) = O(n log n)\n";
                $instructions .= "For n = 1000 transactions:\n";
                $instructions .= "T(1000) = 1000 × log₂(1000)\n";
                $instructions .= "        = 1000 × 9.97\n";
                $instructions .= "        ≈ 9,970 operations\n\n";
                $instructions .= "This means the blockchain validation takes approximately\n";
                $instructions .= "10,000 operations for 1000 votes.\n";
                $instructions .= "```\n\n";
                break;

            case self::TYPE_HARDWARE:
                $instructions .= "```\n";
                $instructions .= "Voltage Divider Calculation:\n";
                $instructions .= "Formula: Vout = Vin × (R2 / (R1 + R2))\n";
                $instructions .= "Given: Vin = 5V, R1 = 10kΩ, R2 = 10kΩ\n";
                $instructions .= "Vout = 5 × (10,000 / (10,000 + 10,000))\n";
                $instructions .= "     = 5 × (10,000 / 20,000)\n";
                $instructions .= "     = 5 × 0.5\n";
                $instructions .= "     = 2.5V\n\n";
                $instructions .= "The output voltage is 2.5V, suitable for the sensor input.\n";
                $instructions .= "```\n\n";
                break;

            case self::TYPE_RESEARCH:
                $instructions .= "```\n";
                $instructions .= "Statistical Significance (t-test):\n";
                $instructions .= "Formula: t = (X̄₁ - X̄₂) / √(s₁²/n₁ + s₂²/n₂)\n";
                $instructions .= "Given: X̄₁ = 75, X̄₂ = 68, s₁ = 12, s₂ = 10, n₁ = n₂ = 30\n";
                $instructions .= "t = (75 - 68) / √(12²/30 + 10²/30)\n";
                $instructions .= "  = 7 / √(144/30 + 100/30)\n";
                $instructions .= "  = 7 / √(4.8 + 3.33)\n";
                $instructions .= "  = 7 / √8.13\n";
                $instructions .= "  = 7 / 2.85\n";
                $instructions .= "  = 2.46\n\n";
                $instructions .= "With t(58) = 2.46, p < 0.05, the difference is statistically significant.\n";
                $instructions .= "```\n\n";
                break;

            case self::TYPE_BUSINESS:
                $instructions .= "```\n";
                $instructions .= "Return on Investment (ROI):\n";
                $instructions .= "Formula: ROI = (Net Profit / Investment Cost) × 100%\n";
                $instructions .= "Given: Net Profit = ₦2,500,000, Investment = ₦10,000,000\n";
                $instructions .= "ROI = (2,500,000 / 10,000,000) × 100%\n";
                $instructions .= "    = 0.25 × 100%\n";
                $instructions .= "    = 25%\n\n";
                $instructions .= "The project yields a 25% return on investment, which exceeds\n";
                $instructions .= "the minimum acceptable ROI of 15%.\n";
                $instructions .= "```\n\n";
                break;
        }

        return $instructions;
    }

    /**
     * Get code generation instructions
     */
    private function getCodeGenerationInstructions(string $projectType): string
    {
        $instructions = "💻 CODE GENERATION REQUIREMENTS:\n";
        $instructions .= "When including code:\n";
        $instructions .= "1. Use proper syntax highlighting with language tags\n";
        $instructions .= "2. Add inline comments explaining complex logic\n";
        $instructions .= "3. Include function/method documentation\n";
        $instructions .= "4. Show realistic, working code (not pseudocode)\n";
        $instructions .= "5. Explain what the code does before showing it\n";
        $instructions .= "6. Discuss key implementation details after the code\n\n";

        $instructions .= "CODE FORMAT:\n";
        $instructions .= "```language\n";
        $instructions .= "// Brief explanation of what this code does\n";
        $instructions .= "code here with comments\n";
        $instructions .= "```\n\n";

        return $instructions;
    }

    /**
     * Get data/table generation instructions
     */
    private function getDataGenerationInstructions(string $projectType): string
    {
        $instructions = "📊 DATA & TABLE GENERATION:\n";
        $instructions .= "Generate realistic sample data in tables:\n";
        $instructions .= "- Use markdown table format\n";
        $instructions .= "- Include appropriate headers\n";
        $instructions .= "- Make data realistic and consistent\n";
        $instructions .= "- Add table captions (Table X.X: Description)\n";
        $instructions .= "- Reference tables in the text\n";
        $instructions .= "- Include units where applicable\n\n";

        return $instructions;
    }

    /**
     * Get placeholder generation instructions
     */
    private function getPlaceholderInstructions(array $capabilities): string
    {
        $instructions = "🖼️ PLACEHOLDER GENERATION:\n";
        $instructions .= "For content you CANNOT generate, create detailed placeholders:\n\n";

        $instructions .= "FORMAT:\n";
        $instructions .= "[FIGURE X.X: Title]\n";
        $instructions .= "**Instructions for Student:** [Detailed step-by-step instructions]\n";
        $instructions .= "- Tool to use: [Specific tool name]\n";
        $instructions .= "- What to show: [Detailed description]\n";
        $instructions .= "- How to create: [Step-by-step guide]\n\n";

        if ($capabilities['needs_screenshots']) {
            $instructions .= "📸 SCREENSHOT PLACEHOLDERS:\n";
            $instructions .= "Create placeholders like:\n";
            $instructions .= "[SCREENSHOT 4.2: User Login Interface]\n";
            $instructions .= "**Instructions:** Capture a screenshot showing:\n";
            $instructions .= "- Username and password input fields\n";
            $instructions .= "- Login button and forgot password link\n";
            $instructions .= "- Application logo and branding\n";
            $instructions .= "Ensure the interface is clean and all elements are visible.\n\n";
        }

        if ($capabilities['needs_circuit_diagrams']) {
            $instructions .= "⚡ CIRCUIT DIAGRAM PLACEHOLDERS:\n";
            $instructions .= "Create placeholders like:\n";
            $instructions .= "[FIGURE 3.4: Arduino Connection Circuit]\n";
            $instructions .= "**Instructions for Student:**\n";
            $instructions .= "1. Use Fritzing, CircuitLab, or EasyEDA to create the circuit\n";
            $instructions .= "2. Show connections for: [list all components]\n";
            $instructions .= "3. Label all pin connections (D2, D3, GND, VCC, etc.)\n";
            $instructions .= "4. Include component values (resistors, capacitors)\n";
            $instructions .= "5. Show power supply connections\n";
            $instructions .= "6. Export as high-resolution PNG (at least 1200px width)\n\n";
        }

        return $instructions;
    }

    /**
     * Count how many keywords match in the text
     */
    private function countKeywordMatches(string $text, array $keywords): int
    {
        $count = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if text contains any of the given keywords
     */
    private function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
