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
