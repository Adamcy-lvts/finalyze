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
