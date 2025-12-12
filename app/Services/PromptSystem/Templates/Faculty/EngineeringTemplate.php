<?php

namespace App\Services\PromptSystem\Templates\Faculty;

use App\Services\PromptSystem\Templates\BasePromptTemplate;

class EngineeringTemplate extends BasePromptTemplate
{
    protected int $priority = 10;

    public function getSystemPrompt(): string
    {
        return parent::getSystemPrompt().<<<'PROMPT'


ENGINEERING-SPECIFIC GUIDELINES:

TECHNICAL WRITING STANDARDS:
- Use precise technical terminology with proper definitions
- Include mathematical formulas using LaTeX format where applicable
- Specify units for all measurements (SI units preferred)
- Reference standards and specifications (IEEE, IEC, ISO)

COMPONENT AND CIRCUIT DOCUMENTATION:
- Include complete component specifications (model numbers, ratings)
- Document all pin connections and voltage levels
- Provide power consumption analysis
- Include safety considerations for high voltage/current

CODE DOCUMENTATION:
- Include properly commented source code
- Show initialization routines and main loops
- Document pin configurations and library dependencies
- Include error handling and edge cases

TESTING AND VALIDATION:
- Document test setup and equipment used
- Include test procedures and pass/fail criteria
- Present results with accuracy/precision analysis
- Compare measured vs expected values
PROMPT;
    }

    protected function getMethodologyInstructions(): string
    {
        return <<<'METHOD'

CHAPTER TYPE: METHODOLOGY (ENGINEERING)

REQUIRED SECTIONS:

3.1 System Design Overview
    - Block diagram of complete system
    - Design philosophy and approach
    - System requirements and specifications

3.2 Hardware Design
    3.2.1 Component Selection
        - Selection criteria for each major component
        - Comparison of alternatives considered
        - Justification for final choices

    3.2.2 Circuit Design
        - Detailed circuit schematic
        - Power supply design
        - Signal conditioning circuits
        - Protection circuits

    3.2.3 Bill of Materials
        - Complete component list with specifications
        - Part numbers and suppliers
        - Cost breakdown

3.3 Software/Firmware Design
    3.3.1 Development Environment
        - IDE and toolchain used
        - Libraries and dependencies

    3.3.2 Program Structure
        - Flowchart of main program
        - Function descriptions
        - Interrupt handling

    3.3.3 Source Code
        - Key code segments with comments
        - Configuration and initialization

3.4 System Integration
    - Assembly procedure
    - Wiring and connections
    - Calibration procedures

3.5 Testing Methodology
    - Test equipment list
    - Test procedures
    - Expected outcomes

REQUIRED TABLES:
- Table 3.1: Bill of Materials (Components, Specifications, Quantity, Cost)
- Table 3.2: Pin Connections (Component, Pin, Connection, Purpose)
- Table 3.3: Power Budget (Component, Voltage, Current, Power)

REQUIRED DIAGRAMS:
- Block diagram of system architecture
- Circuit schematic (placeholder with Fritzing/EasyEDA instructions)
- Flowchart of program operation

METHOD;
    }

    protected function getResultsInstructions(): string
    {
        return <<<'RESULTS'

CHAPTER TYPE: RESULTS (ENGINEERING)

REQUIRED SECTIONS:

4.1 Hardware Implementation Results
    - Photos of completed prototype (placeholder)
    - Description of physical assembly
    - Any modifications from original design

4.2 Functional Testing Results
    4.2.1 Individual Component Testing
        - Test results for each major component
        - Verification of specifications

    4.2.2 System Integration Testing
        - Complete system functionality tests
        - Response times and accuracy

    4.2.3 Performance Metrics
        - Measured vs expected performance
        - Efficiency calculations
        - Reliability testing results

4.3 Test Data Analysis
    - Statistical analysis of measurements
    - Error calculations
    - Performance graphs (generated or placeholder)

4.4 Comparison with Objectives
    - Objective-by-objective evaluation
    - Success/failure for each requirement

4.5 Cost Analysis
    - Final project cost breakdown
    - Comparison with budget

REQUIRED TABLES:
- Table 4.1: Functional Test Results (Test, Expected, Actual, Status)
- Table 4.2: Performance Metrics (Metric, Target, Achieved, Deviation)
- Table 4.3: Comparison with Existing Solutions

SAMPLE DATA NOTE:
For tables with test results, generate realistic sample data based on typical
engineering project outcomes. Mark all sample data clearly:
"THIS IS SAMPLE DATA - Replace with your actual measurements"

RESULTS;
    }

    public function getTableRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            3 => [
                [
                    'type' => 'bill_of_materials',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Complete list of components with specifications and costs',
                    'columns' => ['Component', 'Model/Part No.', 'Specifications', 'Quantity', 'Unit Cost (NGN)', 'Total Cost (NGN)'],
                ],
                [
                    'type' => 'pin_connections',
                    'required' => true,
                    'mock_data' => false,
                    'description' => 'Pin mapping between microcontroller and peripherals',
                    'columns' => ['Microcontroller Pin', 'Connected To', 'Signal Type', 'Description'],
                ],
                [
                    'type' => 'power_budget',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Power consumption analysis for all components',
                    'columns' => ['Component', 'Voltage (V)', 'Current (mA)', 'Power (mW)', 'Mode'],
                ],
            ],
            4 => [
                [
                    'type' => 'test_results',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'Functional test results for each system component',
                    'columns' => ['Test Case', 'Expected Result', 'Actual Result', 'Deviation', 'Status'],
                ],
                [
                    'type' => 'performance_metrics',
                    'required' => true,
                    'mock_data' => true,
                    'description' => 'System performance metrics against targets',
                    'columns' => ['Metric', 'Target', 'Achieved', 'Unit', 'Remarks'],
                ],
                [
                    'type' => 'comparison',
                    'required' => false,
                    'mock_data' => true,
                    'description' => 'Comparison with existing solutions',
                    'columns' => ['Feature', 'This System', 'Solution A', 'Solution B'],
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
                    'type' => 'block_diagram',
                    'required' => true,
                    'can_generate' => true,
                    'description' => 'System block diagram showing all major components',
                    'format' => "graph LR\n    subgraph Input\n        S1[Sensor 1]\n        S2[Sensor 2]\n    end\n    subgraph Processing\n        MCU[Microcontroller]\n    end\n    subgraph Output\n        D[Display]\n        A[Actuator]\n    end\n    S1 --> MCU\n    S2 --> MCU\n    MCU --> D\n    MCU --> A",
                ],
                [
                    'type' => 'circuit_schematic',
                    'required' => true,
                    'can_generate' => false,
                    'description' => 'Complete circuit schematic with all connections',
                    'tool' => 'Fritzing (fritzing.org) or EasyEDA (easyeda.com)',
                ],
                [
                    'type' => 'flowchart',
                    'required' => true,
                    'can_generate' => true,
                    'description' => 'Program operation flowchart',
                    'format' => "flowchart TD\n    A([Start]) --> B[Initialize Hardware]\n    B --> C[Read Sensors]\n    C --> D{Process Data}\n    D --> E[Update Output]\n    E --> F{Continue?}\n    F -->|Yes| C\n    F -->|No| G([End])",
                ],
            ],
            4 => [
                [
                    'type' => 'hardware_photo',
                    'required' => true,
                    'can_generate' => false,
                    'description' => 'Photos of completed hardware prototype',
                    'tool' => 'Camera or Smartphone',
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
                    'Power consumption: P = V × I',
                    'Voltage divider: Vout = Vin × (R2/(R1+R2))',
                    'Current limiting resistor: R = (Vs - Vf) / If',
                    'ADC resolution: Resolution = Vref / 2^n',
                ],
                'examples' => [
                    'Calculate total power consumption',
                    'Calculate LED current limiting resistor',
                    'Calculate sensor reading resolution',
                ],
            ],
            4 => [
                'required' => true,
                'types' => [
                    'Percentage error: Error = ((Measured - Expected) / Expected) × 100',
                    'Efficiency: η = (Output Power / Input Power) × 100',
                    'Standard deviation for repeated measurements',
                ],
                'examples' => [
                    'Calculate measurement accuracy',
                    'Calculate system efficiency',
                    'Analyze test result consistency',
                ],
            ],
            default => [],
        };
    }

    public function getCodeRequirements(int $chapterNumber): array
    {
        return match ($chapterNumber) {
            3 => [
                'required' => true,
                'language' => 'c/cpp/arduino',
                'snippets' => [
                    'Pin definitions and configuration',
                    'Setup and initialization routine',
                    'Main program loop',
                    'Sensor reading functions',
                    'Output control functions',
                ],
            ],
            default => [],
        };
    }

    public function getRecommendedTools(): array
    {
        return [
            'circuit_design' => [
                ['name' => 'Fritzing', 'url' => 'fritzing.org', 'cost' => 'Free', 'best_for' => 'Arduino prototypes'],
                ['name' => 'EasyEDA', 'url' => 'easyeda.com', 'cost' => 'Free', 'best_for' => 'PCB design'],
                ['name' => 'Proteus', 'url' => 'labcenter.com', 'cost' => 'Academic', 'best_for' => 'Simulation'],
            ],
            'programming' => [
                ['name' => 'Arduino IDE', 'url' => 'arduino.cc', 'cost' => 'Free', 'best_for' => 'Arduino programming'],
                ['name' => 'PlatformIO', 'url' => 'platformio.org', 'cost' => 'Free', 'best_for' => 'Professional development'],
            ],
            'documentation' => [
                ['name' => 'Draw.io', 'url' => 'draw.io', 'cost' => 'Free', 'best_for' => 'Diagrams and flowcharts'],
            ],
        ];
    }
}
