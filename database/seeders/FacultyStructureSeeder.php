<?php

namespace Database\Seeders;

use App\Models\FacultyStructure;
use Illuminate\Database\Seeder;

class FacultyStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faculties = [
            $this->getScienceStructure(),
            $this->getEngineeringStructure(),
            $this->getSocialSciencesStructure(),
            $this->getManagementScienceStructure(),
            $this->getMedicalStructure(),
            $this->getLawStructure(),
        ];

        foreach ($faculties as $facultyData) {
            FacultyStructure::updateOrCreate(
                ['faculty_slug' => $facultyData['faculty_slug']],
                $facultyData
            );
        }
    }

    private function getScienceStructure(): array
    {
        return [
            'faculty_name' => 'Science',
            'faculty_slug' => 'science',
            'description' => 'Natural and Physical Sciences - Biology, Chemistry, Physics, Mathematics, etc.',
            'academic_levels' => ['undergraduate', 'masters', 'phd'],
            'sort_order' => 1,
            'default_structure' => [
                'preliminary_pages' => [
                    'title_page' => ['title' => 'Title Page', 'is_required' => true],
                    'certification_page' => ['title' => 'Certification/Approval Page', 'is_required' => true],
                    'dedication' => ['title' => 'Dedication', 'is_required' => false],
                    'acknowledgments' => ['title' => 'Acknowledgments', 'is_required' => true],
                    'abstract' => ['title' => 'Abstract', 'is_required' => true, 'word_count' => 300],
                    'table_of_contents' => ['title' => 'Table of Contents', 'is_required' => true],
                    'list_of_tables' => ['title' => 'List of Tables', 'is_required' => false],
                    'list_of_figures' => ['title' => 'List of Figures', 'is_required' => false],
                    'list_of_abbreviations' => ['title' => 'List of Abbreviations/Glossary', 'is_required' => false],
                ],
                'chapters' => [
                    'default' => [
                        [
                            'number' => 1,
                            'title' => 'Introduction',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 800, 'is_required' => true],
                                ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 500, 'is_required' => true],
                                ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.4', 'title' => 'Research Questions / Hypotheses', 'word_count' => 300, 'is_required' => false],
                                ['number' => '1.5', 'title' => 'Significance of the Study', 'word_count' => 500, 'is_required' => true],
                                ['number' => '1.6', 'title' => 'Scope and Limitations of the Study', 'word_count' => 500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 2,
                            'title' => 'Literature Review',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '2.1', 'title' => 'Conceptual / Theoretical Framework', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.2', 'title' => 'Review of Related Literature', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.3', 'title' => 'Gap in Knowledge', 'word_count' => 500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 3,
                            'title' => 'Research Methodology',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '3.1', 'title' => 'Research Design', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.2', 'title' => 'Study Area / Population', 'word_count' => 500, 'is_required' => false],
                                ['number' => '3.3', 'title' => 'Sampling Techniques', 'word_count' => 500, 'is_required' => true],
                                ['number' => '3.4', 'title' => 'Materials/Instruments Used', 'word_count' => 800, 'is_required' => true],
                                ['number' => '3.5', 'title' => 'Methods / Procedures', 'word_count' => 1000, 'is_required' => true],
                                ['number' => '3.6', 'title' => 'Data Collection Methods', 'word_count' => 400, 'is_required' => true],
                                ['number' => '3.7', 'title' => 'Data Analysis Techniques', 'word_count' => 200, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 4,
                            'title' => 'Results and Discussion',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '4.1', 'title' => 'Presentation of Results', 'word_count' => 2000, 'is_required' => true],
                                ['number' => '4.2', 'title' => 'Analysis and Interpretation', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '4.3', 'title' => 'Discussion of Findings', 'word_count' => 1500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 5,
                            'title' => 'Summary, Conclusion, and Recommendations',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '5.1', 'title' => 'Summary of the Study', 'word_count' => 800, 'is_required' => true],
                                ['number' => '5.2', 'title' => 'Conclusion', 'word_count' => 800, 'is_required' => true],
                                ['number' => '5.3', 'title' => 'Recommendations', 'word_count' => 900, 'is_required' => true],
                            ],
                        ],
                    ],
                ],
                'appendices' => [
                    'references' => ['title' => 'References', 'description' => 'Listed according to departmental style (APA, Harvard, MLA)', 'is_required' => true],
                    'raw_data' => ['title' => 'Raw Data', 'is_required' => false],
                    'questionnaires' => ['title' => 'Questionnaires/Interviews', 'is_required' => false],
                    'lab_protocols' => ['title' => 'Lab Protocols / Calculations', 'is_required' => false],
                    'ethical_clearance' => ['title' => 'Ethical Clearance', 'is_required' => false],
                ],
            ],
            'chapter_templates' => [
                'introduction' => [
                    'description' => 'Chapter One: Introduction - Foundation of your research',
                    'sections' => [
                        ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 800, 'is_required' => true, 'description' => 'Provide context and background information'],
                        ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 500, 'is_required' => true, 'description' => 'Clearly define the research problem'],
                        ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 400, 'is_required' => true, 'description' => 'State the purpose and specific objectives'],
                        ['number' => '1.4', 'title' => 'Research Questions / Hypotheses', 'word_count' => 300, 'is_required' => false, 'description' => 'Formulate research questions or testable hypotheses'],
                        ['number' => '1.5', 'title' => 'Significance of the Study', 'word_count' => 500, 'is_required' => true, 'description' => 'Explain the importance and potential impact'],
                        ['number' => '1.6', 'title' => 'Scope and Limitations of the Study', 'word_count' => 500, 'is_required' => true, 'description' => 'Define boundaries and acknowledge limitations'],
                    ],
                ],
                'literature_review' => [
                    'description' => 'Chapter Two: Literature Review - Review of existing knowledge',
                    'sections' => [
                        ['number' => '2.1', 'title' => 'Conceptual / Theoretical Framework', 'word_count' => 1500, 'is_required' => true, 'description' => 'Present theoretical foundation'],
                        ['number' => '2.2', 'title' => 'Review of Related Literature', 'word_count' => 1500, 'is_required' => true, 'description' => 'Comprehensive review of relevant studies'],
                        ['number' => '2.3', 'title' => 'Gap in Knowledge', 'word_count' => 500, 'is_required' => true, 'description' => 'Identify what is missing in current knowledge'],
                    ],
                ],
                'methodology' => [
                    'description' => 'Chapter Three: Research Methodology - How the research will be conducted',
                    'sections' => [
                        ['number' => '3.1', 'title' => 'Research Design', 'word_count' => 600, 'is_required' => true, 'description' => 'Describe overall research approach'],
                        ['number' => '3.2', 'title' => 'Study Area / Population', 'word_count' => 500, 'is_required' => false, 'description' => 'Define study location and target population'],
                        ['number' => '3.3', 'title' => 'Sampling Techniques', 'word_count' => 500, 'is_required' => true, 'description' => 'Explain sampling methods and sample size'],
                        ['number' => '3.4', 'title' => 'Materials/Instruments Used', 'word_count' => 800, 'is_required' => true, 'description' => 'List and justify materials and equipment'],
                        ['number' => '3.5', 'title' => 'Methods / Procedures', 'word_count' => 1000, 'is_required' => true, 'description' => 'Detailed step-by-step procedures'],
                        ['number' => '3.6', 'title' => 'Data Collection Methods', 'word_count' => 400, 'is_required' => true, 'description' => 'How data will be collected'],
                        ['number' => '3.7', 'title' => 'Data Analysis Techniques', 'word_count' => 200, 'is_required' => true, 'description' => 'Statistical and analytical methods'],
                    ],
                ],
            ],
            'guidance_templates' => [
                'common' => [
                    'preliminary_pages' => [
                        'title' => 'Preliminary Pages Setup',
                        'items' => [
                            'Prepare a professional title page with proper formatting',
                            'Obtain certification/approval signatures from supervisors',
                            'Write a concise abstract (250-300 words) summarizing your study',
                            'Create comprehensive table of contents with page numbers',
                            'Prepare lists of tables, figures, and abbreviations if applicable',
                            'Consider adding dedication and acknowledgments',
                        ],
                    ],
                    'research_design' => [
                        'title' => 'Research Design and Planning',
                        'items' => [
                            'Choose appropriate research design (experimental, observational, etc.)',
                            'Define clear research questions and objectives',
                            'Develop testable hypotheses where applicable',
                            'Plan sampling strategy and determine sample size',
                            'Consider ethical implications and obtain necessary approvals',
                            'Identify potential limitations and how to address them',
                        ],
                    ],
                    'data_collection' => [
                        'title' => 'Data Collection Phase',
                        'items' => [
                            'Calibrate instruments and equipment before use',
                            'Follow standardized protocols and procedures',
                            'Maintain detailed records and laboratory notebooks',
                            'Collect data systematically with proper controls',
                            'Monitor data quality and address issues promptly',
                            'Store data securely with appropriate backups',
                        ],
                    ],
                    'scientific_writing' => [
                        'title' => 'Scientific Writing Standards',
                        'items' => [
                            'Follow departmental style guide (APA, Harvard, MLA)',
                            'Present results objectively with appropriate statistics',
                            'Use clear, concise scientific language',
                            'Include properly formatted tables, figures, and graphs',
                            'Cite all sources appropriately and maintain reference list',
                            'Ensure reproducibility through detailed methodology',
                        ],
                    ],
                ],
            ],
            'terminology' => [
                'common' => [
                    'hypothesis' => 'A testable prediction or explanation for an observation',
                    'control_group' => 'A group in an experiment that does not receive the treatment',
                    'variable' => 'Any factor that can change or be changed in an experiment',
                    'methodology' => 'The systematic approach to conducting research',
                    'peer_review' => 'Evaluation of research by experts in the same field',
                    'replication' => 'Repeating an experiment to verify results',
                ],
            ],
        ];
    }

    private function getEngineeringStructure(): array
    {
        return [
            'faculty_name' => 'Engineering',
            'faculty_slug' => 'engineering',
            'description' => 'Engineering disciplines - Civil, Mechanical, Electrical, Chemical, Computer Engineering, etc.',
            'academic_levels' => ['undergraduate', 'masters', 'phd'],
            'sort_order' => 2,
            'default_structure' => [
                'preliminary_pages' => [
                    'title_page' => ['title' => 'Title Page', 'is_required' => true],
                    'certification_page' => ['title' => 'Certification/Approval Page', 'is_required' => true],
                    'dedication' => ['title' => 'Dedication', 'is_required' => false],
                    'acknowledgments' => ['title' => 'Acknowledgments', 'is_required' => true],
                    'abstract' => ['title' => 'Abstract', 'is_required' => true, 'word_count' => 300],
                    'table_of_contents' => ['title' => 'Table of Contents', 'is_required' => true],
                    'list_of_tables' => ['title' => 'List of Tables', 'is_required' => false],
                    'list_of_figures' => ['title' => 'List of Figures', 'is_required' => false],
                    'list_of_abbreviations' => ['title' => 'List of Abbreviations/Glossary', 'is_required' => false],
                ],
                'chapters' => [
                    'default' => [
                        [
                            'number' => 1,
                            'title' => 'Introduction',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 800, 'is_required' => true],
                                ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 500, 'is_required' => true],
                                ['number' => '1.4', 'title' => 'Significance of the Study', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.5', 'title' => 'Scope and Limitations of the Study', 'word_count' => 500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 2,
                            'title' => 'Literature Review',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '2.1', 'title' => 'Theoretical / Conceptual Framework', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.2', 'title' => 'Review of Related Systems or Works', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.3', 'title' => 'Gap Analysis', 'word_count' => 500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 3,
                            'title' => 'System Analysis and Design',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '3.1', 'title' => 'System Requirements', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '3.2', 'title' => 'System Analysis', 'word_count' => 2000, 'is_required' => true],
                                ['number' => '3.3', 'title' => 'System Design', 'word_count' => 2000, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 4,
                            'title' => 'System Implementation',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '4.1', 'title' => 'Development Tools', 'word_count' => 1000, 'is_required' => true],
                                ['number' => '4.2', 'title' => 'Implementation Details', 'word_count' => 2000, 'is_required' => true],
                                ['number' => '4.3', 'title' => 'Testing and Validation', 'word_count' => 1000, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 5,
                            'title' => 'Results and Evaluation',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '5.1', 'title' => 'Presentation of System Output', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '5.2', 'title' => 'Performance Evaluation', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '5.3', 'title' => 'Comparison with Existing Systems', 'word_count' => 800, 'is_required' => true],
                                ['number' => '5.4', 'title' => 'Discussion of Findings', 'word_count' => 700, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 6,
                            'title' => 'Summary, Conclusion, and Recommendations',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '6.1', 'title' => 'Summary of the Project', 'word_count' => 800, 'is_required' => true],
                                ['number' => '6.2', 'title' => 'Conclusion', 'word_count' => 800, 'is_required' => true],
                                ['number' => '6.3', 'title' => 'Recommendations', 'word_count' => 900, 'is_required' => true],
                            ],
                        ],
                    ],
                ],
                'appendices' => [
                    'references' => ['title' => 'References', 'description' => 'Listed in APA or IEEE style for Engineering', 'is_required' => true],
                    'source_code' => ['title' => 'Source Code', 'description' => 'Complete source code or GitHub link', 'is_required' => true],
                    'user_manual' => ['title' => 'User Manual / Installation Guide', 'is_required' => true],
                    'extra_diagrams' => ['title' => 'Extra Diagrams', 'is_required' => false],
                    'questionnaires' => ['title' => 'Questionnaires / Survey Forms', 'is_required' => false],
                ],
            ],
            'chapter_templates' => [
                'introduction' => [
                    'description' => 'Chapter One: Introduction - Project foundation and scope',
                    'sections' => [
                        ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 800, 'is_required' => true, 'description' => 'Context and background of the engineering problem'],
                        ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 600, 'is_required' => true, 'description' => 'Clear definition of the engineering problem to solve'],
                        ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 500, 'is_required' => true, 'description' => 'Project goals and specific objectives'],
                        ['number' => '1.4', 'title' => 'Significance of the Study', 'word_count' => 600, 'is_required' => true, 'description' => 'Benefits and impact of the proposed solution'],
                        ['number' => '1.5', 'title' => 'Scope and Limitations of the Study', 'word_count' => 500, 'is_required' => true, 'description' => 'Project boundaries and constraints'],
                    ],
                ],
                'literature_review' => [
                    'description' => 'Chapter Two: Literature Review - Related work and gap analysis',
                    'sections' => [
                        ['number' => '2.1', 'title' => 'Theoretical / Conceptual Framework', 'word_count' => 1500, 'is_required' => true, 'description' => 'Theoretical foundation and concepts'],
                        ['number' => '2.2', 'title' => 'Review of Related Systems or Works', 'word_count' => 1500, 'is_required' => true, 'description' => 'Analysis of existing solutions and systems'],
                        ['number' => '2.3', 'title' => 'Gap Analysis', 'word_count' => 500, 'is_required' => true, 'description' => 'Limitations your project addresses'],
                    ],
                ],
                'system_analysis_design' => [
                    'description' => 'Chapter Three: System Analysis and Design - Technical specification',
                    'sections' => [
                        ['number' => '3.1', 'title' => 'System Requirements', 'word_count' => 1500, 'is_required' => true, 'description' => 'Functional and non-functional requirements'],
                        ['number' => '3.2', 'title' => 'System Analysis', 'word_count' => 2000, 'is_required' => true, 'description' => 'Process analysis, use cases, problem domain'],
                        ['number' => '3.3', 'title' => 'System Design', 'word_count' => 2000, 'is_required' => true, 'description' => 'Architecture, diagrams, database schema, algorithms'],
                    ],
                ],
                'implementation' => [
                    'description' => 'Chapter Four: System Implementation - Building the solution',
                    'sections' => [
                        ['number' => '4.1', 'title' => 'Development Tools', 'word_count' => 1000, 'is_required' => true, 'description' => 'Languages, frameworks, hardware, software used'],
                        ['number' => '4.2', 'title' => 'Implementation Details', 'word_count' => 2000, 'is_required' => true, 'description' => 'Modules, features, how system was built'],
                        ['number' => '4.3', 'title' => 'Testing and Validation', 'word_count' => 1000, 'is_required' => true, 'description' => 'Unit, integration, and user acceptance testing'],
                    ],
                ],
            ],
            'guidance_templates' => [
                'common' => [
                    'preliminary_pages' => [
                        'title' => 'Engineering Project Documentation',
                        'items' => [
                            'Prepare professional title page following institutional format',
                            'Obtain supervisor certification and approval signatures',
                            'Write technical abstract highlighting system features and results',
                            'Create detailed table of contents with proper numbering',
                            'Include comprehensive lists of figures, tables, and technical abbreviations',
                            'Consider dedication and acknowledgments sections',
                        ],
                    ],
                    'system_requirements' => [
                        'title' => 'Requirements Engineering',
                        'items' => [
                            'Define functional requirements (what the system should do)',
                            'Specify non-functional requirements (performance, security, usability)',
                            'Conduct stakeholder analysis and user story mapping',
                            'Create use case diagrams and activity flows',
                            'Establish acceptance criteria and testing requirements',
                            'Document constraints and assumptions clearly',
                        ],
                    ],
                    'system_design' => [
                        'title' => 'Engineering Design Process',
                        'items' => [
                            'Design system architecture and component interactions',
                            'Create data flow diagrams and system workflow charts',
                            'Develop UML diagrams (class, sequence, state diagrams)',
                            'Design database schema and data models',
                            'Create algorithm flowcharts and pseudocode',
                            'Design user interface mockups and navigation flows',
                        ],
                    ],
                    'implementation_testing' => [
                        'title' => 'Development and Validation',
                        'items' => [
                            'Follow engineering coding standards and best practices',
                            'Implement modular design with proper documentation',
                            'Conduct unit testing for individual components',
                            'Perform integration testing for system modules',
                            'Execute user acceptance testing with stakeholders',
                            'Document test cases, results, and bug fixes',
                        ],
                    ],
                    'technical_documentation' => [
                        'title' => 'Engineering Documentation Standards',
                        'items' => [
                            'Use IEEE or APA citation style for technical references',
                            'Include source code or GitHub repository links',
                            'Create comprehensive user manual and installation guide',
                            'Document system specifications and technical diagrams',
                            'Prepare performance evaluation with metrics and benchmarks',
                            'Provide recommendations for future enhancements',
                        ],
                    ],
                ],
            ],
            'terminology' => [
                'common' => [
                    'functional_requirement' => 'A specific behavior or function that a system must be able to perform',
                    'non_functional_requirement' => 'Quality attributes like performance, security, and usability',
                    'use_case' => 'A description of how users will perform tasks on your system',
                    'system_architecture' => 'The conceptual model that defines the structure and behavior of a system',
                    'uml_diagram' => 'Unified Modeling Language - standardized diagrams for system design',
                    'data_flow_diagram' => 'Visual representation of how data moves through a system',
                    'unit_testing' => 'Testing individual components or modules in isolation',
                    'integration_testing' => 'Testing the interfaces between components or systems',
                    'user_acceptance_testing' => 'Testing performed by end-users to verify system meets requirements',
                    'algorithm' => 'Step-by-step procedure for solving a problem or completing a task',
                    'api' => 'Application Programming Interface - set of protocols for building software',
                    'database_schema' => 'Structure that represents the logical configuration of a database',
                ],
            ],
        ];
    }

    private function getSocialSciencesStructure(): array
    {
        return [
            'faculty_name' => 'Social Sciences',
            'faculty_slug' => 'social-sciences',
            'description' => 'Social Sciences - Psychology, Sociology, Political Science, Economics, Anthropology, etc.',
            'academic_levels' => ['undergraduate', 'masters', 'phd'],
            'sort_order' => 3,
            'default_structure' => [
                'preliminary_pages' => [
                    'title_page' => ['title' => 'Title Page', 'is_required' => true],
                    'certification_page' => ['title' => 'Certification/Approval Page', 'is_required' => true],
                    'dedication' => ['title' => 'Dedication', 'is_required' => false],
                    'acknowledgments' => ['title' => 'Acknowledgments', 'is_required' => true],
                    'abstract' => ['title' => 'Abstract', 'is_required' => true, 'word_count' => 350],
                    'table_of_contents' => ['title' => 'Table of Contents', 'is_required' => true],
                    'list_of_tables' => ['title' => 'List of Tables', 'is_required' => false],
                    'list_of_figures' => ['title' => 'List of Figures', 'is_required' => false],
                    'list_of_abbreviations' => ['title' => 'List of Abbreviations', 'is_required' => false],
                ],
                'chapters' => [
                    'default' => [
                        [
                            'number' => 1,
                            'title' => 'Introduction',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 500, 'is_required' => true],
                                ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.4', 'title' => 'Research Questions', 'word_count' => 300, 'is_required' => true],
                                ['number' => '1.5', 'title' => 'Research Hypotheses', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.6', 'title' => 'Significance of the Study', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.7', 'title' => 'Scope of the Study', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.8', 'title' => 'Limitations of the Study', 'word_count' => 400, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 2,
                            'title' => 'Literature Review',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '2.1', 'title' => 'Conceptual Framework', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.2', 'title' => 'Theoretical Framework', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.3', 'title' => 'Empirical Review', 'word_count' => 2000, 'is_required' => true],
                                ['number' => '2.4', 'title' => 'Gap in Knowledge', 'word_count' => 500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 3,
                            'title' => 'Research Methodology',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '3.1', 'title' => 'Research Design', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.2', 'title' => 'Population of the Study', 'word_count' => 500, 'is_required' => true],
                                ['number' => '3.3', 'title' => 'Sample Size and Sampling Techniques', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.4', 'title' => 'Sources of Data', 'word_count' => 400, 'is_required' => true],
                                ['number' => '3.5', 'title' => 'Research Instruments', 'word_count' => 700, 'is_required' => true],
                                ['number' => '3.6', 'title' => 'Validity and Reliability of Instruments', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.7', 'title' => 'Method of Data Collection', 'word_count' => 500, 'is_required' => true],
                                ['number' => '3.8', 'title' => 'Method of Data Analysis', 'word_count' => 600, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 4,
                            'title' => 'Data Presentation, Analysis, and Interpretation',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '4.1', 'title' => 'Presentation of Data', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '4.2', 'title' => 'Analysis of Research Questions', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '4.3', 'title' => 'Test of Hypotheses', 'word_count' => 1000, 'is_required' => true],
                                ['number' => '4.4', 'title' => 'Discussion of Findings', 'word_count' => 1000, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 5,
                            'title' => 'Summary, Conclusion, and Recommendations',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '5.1', 'title' => 'Summary of Findings', 'word_count' => 1000, 'is_required' => true],
                                ['number' => '5.2', 'title' => 'Conclusion', 'word_count' => 800, 'is_required' => true],
                                ['number' => '5.3', 'title' => 'Recommendations', 'word_count' => 800, 'is_required' => true],
                                ['number' => '5.4', 'title' => 'Suggestions for Further Research', 'word_count' => 400, 'is_required' => false],
                            ],
                        ],
                    ],
                ],
                'appendices' => [
                    'references' => ['title' => 'References', 'description' => 'Listed in APA style (most common in Nigerian social sciences)', 'is_required' => true],
                    'questionnaire' => ['title' => 'Questionnaire / Interview Guide', 'is_required' => true],
                    'spss_output' => ['title' => 'SPSS/Excel Output Tables', 'is_required' => false],
                    'ethical_approval' => ['title' => 'Ethical Approval', 'is_required' => false],
                ],
            ],
            'chapter_templates' => [
                'introduction' => [
                    'description' => 'Chapter One: Introduction - Research foundation and framework',
                    'sections' => [
                        ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 600, 'is_required' => true, 'description' => 'Context and background information on the research area'],
                        ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 500, 'is_required' => true, 'description' => 'Clear articulation of the research problem'],
                        ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 400, 'is_required' => true, 'description' => 'General aim and specific objectives'],
                        ['number' => '1.4', 'title' => 'Research Questions', 'word_count' => 300, 'is_required' => true, 'description' => 'Specific questions the study seeks to answer'],
                        ['number' => '1.5', 'title' => 'Research Hypotheses', 'word_count' => 400, 'is_required' => true, 'description' => 'Testable statements for statistical analysis'],
                        ['number' => '1.6', 'title' => 'Significance of the Study', 'word_count' => 600, 'is_required' => true, 'description' => 'Importance and contributions of the study'],
                        ['number' => '1.7', 'title' => 'Scope of the Study', 'word_count' => 400, 'is_required' => true, 'description' => 'Boundaries and coverage of the research'],
                        ['number' => '1.8', 'title' => 'Limitations of the Study', 'word_count' => 400, 'is_required' => true, 'description' => 'Constraints and limitations faced'],
                    ],
                ],
                'literature_review' => [
                    'description' => 'Chapter Two: Literature Review - Theoretical foundation and related studies',
                    'sections' => [
                        ['number' => '2.1', 'title' => 'Conceptual Framework', 'word_count' => 1500, 'is_required' => true, 'description' => 'Definition of key concepts, models, and theories'],
                        ['number' => '2.2', 'title' => 'Theoretical Framework', 'word_count' => 1500, 'is_required' => true, 'description' => 'Relevant theories underpinning the study'],
                        ['number' => '2.3', 'title' => 'Empirical Review', 'word_count' => 2000, 'is_required' => true, 'description' => 'Summary of findings from past related studies'],
                        ['number' => '2.4', 'title' => 'Gap in Knowledge', 'word_count' => 500, 'is_required' => true, 'description' => 'Where your study contributes to knowledge'],
                    ],
                ],
                'methodology' => [
                    'description' => 'Chapter Three: Research Methodology - Research approach and procedures',
                    'sections' => [
                        ['number' => '3.1', 'title' => 'Research Design', 'word_count' => 600, 'is_required' => true, 'description' => 'Survey, case study, historical, ex-post facto, etc.'],
                        ['number' => '3.2', 'title' => 'Population of the Study', 'word_count' => 500, 'is_required' => true, 'description' => 'Target population characteristics'],
                        ['number' => '3.3', 'title' => 'Sample Size and Sampling Techniques', 'word_count' => 600, 'is_required' => true, 'description' => 'Sample determination and selection methods'],
                        ['number' => '3.4', 'title' => 'Sources of Data', 'word_count' => 400, 'is_required' => true, 'description' => 'Primary and secondary data sources'],
                        ['number' => '3.5', 'title' => 'Research Instruments', 'word_count' => 700, 'is_required' => true, 'description' => 'Questionnaire, interview, observation, documents'],
                        ['number' => '3.6', 'title' => 'Validity and Reliability of Instruments', 'word_count' => 600, 'is_required' => true, 'description' => 'Instrument validation procedures'],
                        ['number' => '3.7', 'title' => 'Method of Data Collection', 'word_count' => 500, 'is_required' => true, 'description' => 'Data collection procedures'],
                        ['number' => '3.8', 'title' => 'Method of Data Analysis', 'word_count' => 600, 'is_required' => true, 'description' => 'Statistical tools: regression, chi-square, ANOVA, etc.'],
                    ],
                ],
            ],
            'guidance_templates' => [
                'common' => [
                    'research_design' => [
                        'title' => 'Social Science Research Design',
                        'items' => [
                            'Choose appropriate research design (survey, case study, experimental)',
                            'Define research paradigm (positivist, interpretivist, pragmatic)',
                            'Formulate clear research questions and testable hypotheses',
                            'Consider ethical implications and obtain necessary approvals',
                            'Plan data collection strategy and timeline',
                            'Select appropriate statistical analysis methods',
                        ],
                    ],
                    'theoretical_framework' => [
                        'title' => 'Developing Theoretical Foundation',
                        'items' => [
                            'Identify and define key concepts relevant to your study',
                            'Review relevant theories in your discipline',
                            'Explain how theories relate to your research questions',
                            'Develop conceptual model or framework',
                            'Justify theoretical choices and applications',
                            'Show relationships between variables',
                        ],
                    ],
                    'data_analysis' => [
                        'title' => 'Statistical Analysis Guidelines',
                        'items' => [
                            'Choose appropriate statistical tests for your data type',
                            'Use statistical software (SPSS, R, Excel) effectively',
                            'Present data using tables, charts, and graphs',
                            'Test hypotheses using appropriate significance levels',
                            'Interpret statistical results in context of research objectives',
                            'Discuss findings in relation to existing literature',
                        ],
                    ],
                    'academic_writing' => [
                        'title' => 'Social Science Writing Standards',
                        'items' => [
                            'Follow APA citation style consistently',
                            'Write clearly and objectively',
                            'Use appropriate academic tone and language',
                            'Present balanced arguments and acknowledge limitations',
                            'Ensure logical flow between chapters and sections',
                            'Proofread for grammar, spelling, and formatting',
                        ],
                    ],
                ],
            ],
            'terminology' => [
                'common' => [
                    'research_paradigm' => 'A framework of beliefs and assumptions about research',
                    'conceptual_framework' => 'A structure of concepts and their relationships',
                    'theoretical_framework' => 'A structure based on existing theories',
                    'empirical_review' => 'Review of studies based on observation or experience',
                    'hypothesis' => 'A testable statement about the relationship between variables',
                    'population' => 'The entire group of individuals being studied',
                    'sample' => 'A subset of the population selected for study',
                    'validity' => 'The extent to which an instrument measures what it claims to measure',
                    'reliability' => 'The consistency of a measurement instrument',
                    'variable' => 'Any characteristic that can take on different values',
                    'correlation' => 'A statistical measure of the relationship between variables',
                    'significance_level' => 'The probability of rejecting a true null hypothesis',
                ],
            ],
        ];
    }

    private function getManagementScienceStructure(): array
    {
        return [
            'faculty_name' => 'Management Science',
            'faculty_slug' => 'management-science',
            'description' => 'Business and Management - Business Administration, Finance, Marketing, Operations, etc.',
            'academic_levels' => ['undergraduate', 'masters', 'phd'],
            'sort_order' => 4,
            'default_structure' => [
                'preliminary_pages' => [
                    'title_page' => ['title' => 'Title Page', 'is_required' => true],
                    'certification_page' => ['title' => 'Certification/Approval Page', 'is_required' => true],
                    'dedication' => ['title' => 'Dedication', 'is_required' => false],
                    'acknowledgments' => ['title' => 'Acknowledgments', 'is_required' => true],
                    'abstract' => ['title' => 'Abstract', 'is_required' => true, 'word_count' => 350],
                    'table_of_contents' => ['title' => 'Table of Contents', 'is_required' => true],
                    'list_of_tables' => ['title' => 'List of Tables', 'is_required' => false],
                    'list_of_figures' => ['title' => 'List of Figures', 'is_required' => false],
                    'list_of_abbreviations' => ['title' => 'List of Abbreviations', 'is_required' => false],
                ],
                'chapters' => [
                    'default' => [
                        [
                            'number' => 1,
                            'title' => 'Introduction',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 500, 'is_required' => true],
                                ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.4', 'title' => 'Research Questions', 'word_count' => 300, 'is_required' => true],
                                ['number' => '1.5', 'title' => 'Research Hypotheses', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.6', 'title' => 'Significance of the Study', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.7', 'title' => 'Scope of the Study', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.8', 'title' => 'Limitations of the Study', 'word_count' => 400, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 2,
                            'title' => 'Literature Review',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '2.1', 'title' => 'Conceptual Framework', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.2', 'title' => 'Theoretical Framework', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.3', 'title' => 'Empirical Review', 'word_count' => 2000, 'is_required' => true],
                                ['number' => '2.4', 'title' => 'Gap in Knowledge', 'word_count' => 500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 3,
                            'title' => 'Research Methodology',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '3.1', 'title' => 'Research Design', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.2', 'title' => 'Population of the Study', 'word_count' => 500, 'is_required' => true],
                                ['number' => '3.3', 'title' => 'Sample Size and Sampling Techniques', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.4', 'title' => 'Sources of Data', 'word_count' => 400, 'is_required' => true],
                                ['number' => '3.5', 'title' => 'Research Instruments', 'word_count' => 700, 'is_required' => true],
                                ['number' => '3.6', 'title' => 'Validity and Reliability of Instruments', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.7', 'title' => 'Method of Data Collection', 'word_count' => 500, 'is_required' => true],
                                ['number' => '3.8', 'title' => 'Method of Data Analysis', 'word_count' => 600, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 4,
                            'title' => 'Data Presentation, Analysis, and Interpretation',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '4.1', 'title' => 'Presentation of Data', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '4.2', 'title' => 'Analysis of Research Questions', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '4.3', 'title' => 'Test of Hypotheses', 'word_count' => 1000, 'is_required' => true],
                                ['number' => '4.4', 'title' => 'Discussion of Findings', 'word_count' => 1000, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 5,
                            'title' => 'Summary, Conclusion, and Recommendations',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '5.1', 'title' => 'Summary of Findings', 'word_count' => 1000, 'is_required' => true],
                                ['number' => '5.2', 'title' => 'Conclusion', 'word_count' => 800, 'is_required' => true],
                                ['number' => '5.3', 'title' => 'Recommendations', 'word_count' => 800, 'is_required' => true],
                                ['number' => '5.4', 'title' => 'Suggestions for Further Research', 'word_count' => 400, 'is_required' => false],
                            ],
                        ],
                    ],
                ],
                'appendices' => [
                    'references' => ['title' => 'References', 'description' => 'Listed in APA style (most common in Nigerian management studies)', 'is_required' => true],
                    'questionnaire' => ['title' => 'Questionnaire / Interview Guide', 'is_required' => true],
                    'spss_output' => ['title' => 'SPSS/Excel Output Tables', 'is_required' => false],
                    'ethical_approval' => ['title' => 'Ethical Approval', 'is_required' => false],
                ],
            ],
            'chapter_templates' => [
                'introduction' => [
                    'description' => 'Chapter One: Introduction - Business research foundation',
                    'sections' => [
                        ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 600, 'is_required' => true, 'description' => 'Context and background of the business problem'],
                        ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 500, 'is_required' => true, 'description' => 'Clear articulation of the business research problem'],
                        ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 400, 'is_required' => true, 'description' => 'Business research goals and objectives'],
                        ['number' => '1.4', 'title' => 'Research Questions', 'word_count' => 300, 'is_required' => true, 'description' => 'Specific business questions to investigate'],
                        ['number' => '1.5', 'title' => 'Research Hypotheses', 'word_count' => 400, 'is_required' => true, 'description' => 'Testable business hypotheses'],
                        ['number' => '1.6', 'title' => 'Significance of the Study', 'word_count' => 600, 'is_required' => true, 'description' => 'Business and academic importance'],
                        ['number' => '1.7', 'title' => 'Scope of the Study', 'word_count' => 400, 'is_required' => true, 'description' => 'Boundaries of the business research'],
                        ['number' => '1.8', 'title' => 'Limitations of the Study', 'word_count' => 400, 'is_required' => true, 'description' => 'Research constraints and limitations'],
                    ],
                ],
                'literature_review' => [
                    'description' => 'Chapter Two: Literature Review - Business theories and related studies',
                    'sections' => [
                        ['number' => '2.1', 'title' => 'Conceptual Framework', 'word_count' => 1500, 'is_required' => true, 'description' => 'Key business concepts and models'],
                        ['number' => '2.2', 'title' => 'Theoretical Framework', 'word_count' => 1500, 'is_required' => true, 'description' => 'Relevant business and management theories'],
                        ['number' => '2.3', 'title' => 'Empirical Review', 'word_count' => 2000, 'is_required' => true, 'description' => 'Review of related business studies'],
                        ['number' => '2.4', 'title' => 'Gap in Knowledge', 'word_count' => 500, 'is_required' => true, 'description' => 'Business research gap identified'],
                    ],
                ],
                'methodology' => [
                    'description' => 'Chapter Three: Research Methodology - Business research methods',
                    'sections' => [
                        ['number' => '3.1', 'title' => 'Research Design', 'word_count' => 600, 'is_required' => true, 'description' => 'Business research approach (survey, case study, etc.)'],
                        ['number' => '3.2', 'title' => 'Population of the Study', 'word_count' => 500, 'is_required' => true, 'description' => 'Target business population'],
                        ['number' => '3.3', 'title' => 'Sample Size and Sampling Techniques', 'word_count' => 600, 'is_required' => true, 'description' => 'Business sampling methods'],
                        ['number' => '3.4', 'title' => 'Sources of Data', 'word_count' => 400, 'is_required' => true, 'description' => 'Primary and secondary business data'],
                        ['number' => '3.5', 'title' => 'Research Instruments', 'word_count' => 700, 'is_required' => true, 'description' => 'Business data collection tools'],
                        ['number' => '3.6', 'title' => 'Validity and Reliability of Instruments', 'word_count' => 600, 'is_required' => true, 'description' => 'Instrument validation for business context'],
                        ['number' => '3.7', 'title' => 'Method of Data Collection', 'word_count' => 500, 'is_required' => true, 'description' => 'Business data collection procedures'],
                        ['number' => '3.8', 'title' => 'Method of Data Analysis', 'word_count' => 600, 'is_required' => true, 'description' => 'Business statistics and analysis methods'],
                    ],
                ],
            ],
            'guidance_templates' => [
                'common' => [
                    'business_research_design' => [
                        'title' => 'Business Research Design',
                        'items' => [
                            'Identify clear business problem or opportunity',
                            'Choose appropriate business research methodology',
                            'Define target market or business population',
                            'Consider industry context and business environment',
                            'Plan for practical business applications of findings',
                            'Address ethical considerations in business research',
                        ],
                    ],
                    'business_theories' => [
                        'title' => 'Business and Management Theories',
                        'items' => [
                            'Review relevant management and business theories',
                            'Apply theoretical frameworks to business context',
                            'Consider multiple business perspectives and models',
                            'Relate theories to practical business applications',
                            'Justify theoretical choices for business research',
                            'Develop business-specific conceptual frameworks',
                        ],
                    ],
                    'business_data_analysis' => [
                        'title' => 'Business Data Analysis',
                        'items' => [
                            'Use appropriate statistical methods for business data',
                            'Apply business metrics and performance indicators',
                            'Analyze financial and operational data effectively',
                            'Present business findings with charts and graphs',
                            'Interpret results in business context',
                            'Provide actionable business recommendations',
                        ],
                    ],
                    'business_writing' => [
                        'title' => 'Business Academic Writing',
                        'items' => [
                            'Follow APA citation style for business sources',
                            'Use professional business language and terminology',
                            'Present balanced business arguments and analysis',
                            'Include practical business implications',
                            'Maintain objectivity in business research reporting',
                            'Ensure recommendations are actionable and realistic',
                        ],
                    ],
                ],
            ],
            'terminology' => [
                'common' => [
                    'stakeholder' => 'Any individual or group affected by business decisions',
                    'roi' => 'Return on Investment - measure of investment efficiency',
                    'kpi' => 'Key Performance Indicator - measurable value showing effectiveness',
                    'swot_analysis' => 'Strengths, Weaknesses, Opportunities, Threats analysis framework',
                    'business_model' => 'Framework for how a company creates and delivers value',
                    'market_research' => 'Systematic gathering of information about target markets',
                    'competitive_advantage' => 'Factors that allow a company to outperform competitors',
                    'organizational_behavior' => 'Study of how people interact within business organizations',
                    'strategic_management' => 'Planning and execution of business strategies',
                    'financial_analysis' => 'Assessment of business financial performance and viability',
                    'operational_efficiency' => 'Measure of how well business operations convert inputs to outputs',
                    'customer_satisfaction' => 'Measure of how well products/services meet customer expectations',
                ],
            ],
        ];
    }

    private function getMedicalStructure(): array
    {
        return [
            'faculty_name' => 'Medical',
            'faculty_slug' => 'medical',
            'description' => 'Medical and Health Sciences - Medicine, Nursing, Pharmacy, Public Health, etc.',
            'academic_levels' => ['undergraduate', 'masters', 'phd'],
            'sort_order' => 5,
            'default_structure' => [
                'preliminary_pages' => [
                    'title_page' => ['title' => 'Title Page', 'is_required' => true],
                    'certification_page' => ['title' => 'Certification/Approval Page', 'is_required' => true],
                    'dedication' => ['title' => 'Dedication', 'is_required' => false],
                    'acknowledgments' => ['title' => 'Acknowledgments', 'is_required' => true],
                    'abstract' => ['title' => 'Abstract', 'is_required' => true, 'word_count' => 300, 'description' => 'Structured: Background, Method, Results, Conclusion'],
                    'table_of_contents' => ['title' => 'Table of Contents', 'is_required' => true],
                    'list_of_tables' => ['title' => 'List of Tables', 'is_required' => false],
                    'list_of_figures' => ['title' => 'List of Figures', 'is_required' => false],
                    'list_of_abbreviations' => ['title' => 'List of Abbreviations/Glossary', 'is_required' => true],
                ],
                'chapters' => [
                    'default' => [
                        [
                            'number' => 1,
                            'title' => 'Introduction',
                            'word_count' => 3500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 700, 'is_required' => true],
                                ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 500, 'is_required' => true],
                                ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.4', 'title' => 'Research Questions / Hypotheses', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.5', 'title' => 'Justification/Significance of the Study', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.6', 'title' => 'Scope of the Study', 'word_count' => 400, 'is_required' => true],
                                ['number' => '1.7', 'title' => 'Limitations', 'word_count' => 300, 'is_required' => false],
                            ],
                        ],
                        [
                            'number' => 2,
                            'title' => 'Literature Review',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '2.1', 'title' => 'Conceptual / Theoretical Framework', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.2', 'title' => 'Review of Related Studies', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.3', 'title' => 'Summary of Gaps in Knowledge', 'word_count' => 500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 3,
                            'title' => 'Research Methodology',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '3.1', 'title' => 'Study Design', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.2', 'title' => 'Study Area', 'word_count' => 400, 'is_required' => true],
                                ['number' => '3.3', 'title' => 'Target Population', 'word_count' => 500, 'is_required' => true],
                                ['number' => '3.4', 'title' => 'Sample Size and Sampling Techniques', 'word_count' => 700, 'is_required' => true],
                                ['number' => '3.5', 'title' => 'Inclusion and Exclusion Criteria', 'word_count' => 500, 'is_required' => true],
                                ['number' => '3.6', 'title' => 'Ethical Considerations', 'word_count' => 800, 'is_required' => true],
                                ['number' => '3.7', 'title' => 'Materials / Instruments', 'word_count' => 700, 'is_required' => true],
                                ['number' => '3.8', 'title' => 'Data Collection Procedure', 'word_count' => 600, 'is_required' => true],
                                ['number' => '3.9', 'title' => 'Data Analysis', 'word_count' => 700, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 4,
                            'title' => 'Results',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '4.1', 'title' => 'Socio-demographic Characteristics', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '4.2', 'title' => 'Laboratory / Clinical Findings', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '4.3', 'title' => 'Tables, Graphs, and Charts of Results', 'word_count' => 1000, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 5,
                            'title' => 'Discussion',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '5.1', 'title' => 'Interpretation of Results', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '5.2', 'title' => 'Comparison with Related Studies', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '5.3', 'title' => 'Implications of Findings', 'word_count' => 1500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 6,
                            'title' => 'Summary, Conclusion, and Recommendations',
                            'word_count' => 1500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '6.1', 'title' => 'Summary of the Study', 'word_count' => 1000, 'is_required' => true],
                                ['number' => '6.2', 'title' => 'Conclusion', 'word_count' => 800, 'is_required' => true],
                                ['number' => '6.3', 'title' => 'Recommendations', 'word_count' => 1200, 'is_required' => true],
                            ],
                        ],
                    ],
                ],
                'appendices' => [
                    'references' => ['title' => 'References', 'description' => 'APA or Vancouver referencing style (many medical schools use Vancouver)', 'is_required' => true],
                    'ethical_approval' => ['title' => 'Ethical Approval Letter', 'is_required' => true],
                    'consent_forms' => ['title' => 'Patient Consent Forms', 'description' => 'If applicable', 'is_required' => false],
                    'questionnaires' => ['title' => 'Questionnaires/Interview Guides', 'is_required' => false],
                    'lab_protocols' => ['title' => 'Lab Protocols', 'is_required' => false],
                    'raw_data' => ['title' => 'Raw Data Tables', 'is_required' => false],
                ],
            ],
            'chapter_templates' => [
                'introduction' => [
                    'description' => 'Chapter One: Introduction - Medical research foundation',
                    'sections' => [
                        ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 700, 'is_required' => true, 'description' => 'Medical context and background of the health issue'],
                        ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 500, 'is_required' => true, 'description' => 'Clear articulation of the health problem'],
                        ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 600, 'is_required' => true, 'description' => 'Medical research goals and specific objectives'],
                        ['number' => '1.4', 'title' => 'Research Questions / Hypotheses', 'word_count' => 400, 'is_required' => true, 'description' => 'Specific medical questions or testable hypotheses'],
                        ['number' => '1.5', 'title' => 'Justification/Significance of the Study', 'word_count' => 600, 'is_required' => true, 'description' => 'Clinical and public health importance'],
                        ['number' => '1.6', 'title' => 'Scope of the Study', 'word_count' => 400, 'is_required' => true, 'description' => 'Boundaries of the medical research'],
                        ['number' => '1.7', 'title' => 'Limitations', 'word_count' => 300, 'is_required' => false, 'description' => 'Study constraints and limitations'],
                    ],
                ],
                'literature_review' => [
                    'description' => 'Chapter Two: Literature Review - Medical and health literature',
                    'sections' => [
                        ['number' => '2.1', 'title' => 'Conceptual / Theoretical Framework', 'word_count' => 1500, 'is_required' => true, 'description' => 'Medical theories and conceptual foundations'],
                        ['number' => '2.2', 'title' => 'Review of Related Studies', 'word_count' => 1500, 'is_required' => true, 'description' => 'Epidemiological, clinical, or lab-based studies'],
                        ['number' => '2.3', 'title' => 'Summary of Gaps in Knowledge', 'word_count' => 500, 'is_required' => true, 'description' => 'Medical knowledge gaps identified'],
                    ],
                ],
                'methodology' => [
                    'description' => 'Chapter Three: Research Methodology - Medical research methods',
                    'sections' => [
                        ['number' => '3.1', 'title' => 'Study Design', 'word_count' => 600, 'is_required' => true, 'description' => 'Cross-sectional, cohort, experimental, case-control, RCT, etc.'],
                        ['number' => '3.2', 'title' => 'Study Area', 'word_count' => 400, 'is_required' => true, 'description' => 'Hospital, community, lab, region'],
                        ['number' => '3.3', 'title' => 'Target Population', 'word_count' => 500, 'is_required' => true, 'description' => 'Patient population and demographics'],
                        ['number' => '3.4', 'title' => 'Sample Size and Sampling Techniques', 'word_count' => 700, 'is_required' => true, 'description' => 'Statistical power and sampling methods'],
                        ['number' => '3.5', 'title' => 'Inclusion and Exclusion Criteria', 'word_count' => 500, 'is_required' => true, 'description' => 'Patient selection criteria'],
                        ['number' => '3.6', 'title' => 'Ethical Considerations', 'word_count' => 800, 'is_required' => true, 'description' => 'IRB/ethics committee approval, informed consent'],
                        ['number' => '3.7', 'title' => 'Materials / Instruments', 'word_count' => 700, 'is_required' => true, 'description' => 'Lab kits, devices, questionnaires, diagnostic tools'],
                        ['number' => '3.8', 'title' => 'Data Collection Procedure', 'word_count' => 600, 'is_required' => true, 'description' => 'Step-by-step data collection protocol'],
                        ['number' => '3.9', 'title' => 'Data Analysis', 'word_count' => 700, 'is_required' => true, 'description' => 'Statistical tools: SPSS, Epi Info, Stata, R, etc.'],
                    ],
                ],
            ],
            'guidance_templates' => [
                'common' => [
                    'medical_research_ethics' => [
                        'title' => 'Medical Research Ethics and Compliance',
                        'items' => [
                            'Obtain ethical approval from Institutional Review Board (IRB) or ethics committee',
                            'Ensure informed consent procedures for all human subjects',
                            'Maintain strict patient confidentiality and data protection',
                            'Follow Good Clinical Practice (GCP) guidelines',
                            'Adhere to Declaration of Helsinki principles',
                            'Document all ethical considerations and approvals clearly',
                        ],
                    ],
                    'clinical_study_design' => [
                        'title' => 'Clinical Study Design and Methods',
                        'items' => [
                            'Choose appropriate study design (observational vs experimental)',
                            'Define clear inclusion and exclusion criteria for participants',
                            'Calculate adequate sample size with statistical power analysis',
                            'Establish standardized protocols for data collection',
                            'Plan for potential confounding variables and bias',
                            'Consider multicenter vs single-center study implications',
                        ],
                    ],
                    'medical_data_analysis' => [
                        'title' => 'Medical Statistics and Data Analysis',
                        'items' => [
                            'Use appropriate statistical tests for medical data types',
                            'Consider clinical significance alongside statistical significance',
                            'Apply survival analysis methods for time-to-event data',
                            'Handle missing data appropriately in clinical datasets',
                            'Use medical statistical software (SPSS, Stata, R, Epi Info)',
                            'Present results with confidence intervals and effect sizes',
                        ],
                    ],
                    'clinical_interpretation' => [
                        'title' => 'Clinical Results Interpretation',
                        'items' => [
                            'Interpret findings in context of existing medical literature',
                            'Discuss clinical relevance and practical implications',
                            'Address limitations and potential sources of bias',
                            'Consider generalizability to broader patient populations',
                            'Recommend clinical practice changes based on evidence',
                            'Suggest directions for future medical research',
                        ],
                    ],
                    'medical_writing' => [
                        'title' => 'Medical Academic Writing Standards',
                        'items' => [
                            'Follow Vancouver or APA citation style for medical literature',
                            'Use precise medical terminology and standardized abbreviations',
                            'Structure abstract with Background, Methods, Results, Conclusion',
                            'Present data clearly with appropriate tables and figures',
                            'Maintain objectivity in reporting clinical findings',
                            'Include comprehensive list of medical abbreviations',
                        ],
                    ],
                ],
            ],
            'terminology' => [
                'common' => [
                    'informed_consent' => 'Voluntary agreement to participate after understanding risks and benefits',
                    'institutional_review_board' => 'Ethics committee that reviews and approves research protocols',
                    'clinical_trial' => 'Research study testing medical treatments on human participants',
                    'randomized_controlled_trial' => 'Experimental study with random assignment to treatment groups',
                    'epidemiology' => 'Study of disease distribution and determinants in populations',
                    'incidence' => 'Number of new cases of disease occurring in a specific time period',
                    'prevalence' => 'Total number of cases of disease in a population at a given time',
                    'case_control_study' => 'Observational study comparing those with and without disease',
                    'cohort_study' => 'Observational study following groups over time',
                    'cross_sectional_study' => 'Observational study examining data at a single point in time',
                    'confounding_variable' => 'Factor that influences both exposure and outcome',
                    'bias' => 'Systematic error in study design, conduct, or analysis',
                    'sensitivity' => 'Ability of a test to correctly identify those with disease',
                    'specificity' => 'Ability of a test to correctly identify those without disease',
                    'evidence_based_medicine' => 'Medical practice based on best available scientific evidence',
                    'clinical_significance' => 'Practical importance of treatment effect in clinical practice',
                ],
            ],
        ];
    }

    private function getLawStructure(): array
    {
        return [
            'faculty_name' => 'Law',
            'faculty_slug' => 'law',
            'description' => 'Legal Studies - Constitutional Law, Criminal Law, Corporate Law, International Law, etc.',
            'academic_levels' => ['undergraduate', 'masters', 'phd'],
            'sort_order' => 6,
            'default_structure' => [
                'preliminary_pages' => [
                    'title_page' => ['title' => 'Title Page', 'is_required' => true],
                    'certification_page' => ['title' => 'Certification/Approval Page', 'is_required' => true],
                    'dedication' => ['title' => 'Dedication', 'is_required' => false],
                    'acknowledgments' => ['title' => 'Acknowledgments', 'is_required' => true],
                    'abstract' => ['title' => 'Abstract', 'is_required' => true, 'word_count' => 400],
                    'table_of_contents' => ['title' => 'Table of Contents', 'is_required' => true],
                    'table_of_cases' => ['title' => 'Table of Cases', 'is_required' => true],
                    'table_of_statutes' => ['title' => 'Table of Statutes', 'is_required' => true],
                    'list_of_abbreviations' => ['title' => 'List of Abbreviations', 'is_required' => true],
                ],
                'chapters' => [
                    'default' => [
                        [
                            'number' => 1,
                            'title' => 'General Introduction',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 700, 'is_required' => true],
                                ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 500, 'is_required' => true],
                                ['number' => '1.4', 'title' => 'Research Questions', 'word_count' => 300, 'is_required' => false],
                                ['number' => '1.5', 'title' => 'Significance of the Study', 'word_count' => 600, 'is_required' => true],
                                ['number' => '1.6', 'title' => 'Scope of the Study', 'word_count' => 500, 'is_required' => true],
                                ['number' => '1.7', 'title' => 'Methodology', 'word_count' => 800, 'is_required' => true],
                                ['number' => '1.8', 'title' => 'Literature Review', 'word_count' => 500, 'is_required' => false],
                            ],
                        ],
                        [
                            'number' => 2,
                            'title' => 'Literature Review and Thematic Discussions',
                            'word_count' => 6000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '2.1', 'title' => 'Review of Doctrines and Principles', 'word_count' => 2000, 'is_required' => true],
                                ['number' => '2.2', 'title' => 'Statutory Framework Analysis', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.3', 'title' => 'Case Law Review', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '2.4', 'title' => 'Theoretical Frameworks', 'word_count' => 800, 'is_required' => true],
                                ['number' => '2.5', 'title' => 'Comparative Legal Perspectives', 'word_count' => 1200, 'is_required' => false],
                            ],
                        ],
                        [
                            'number' => 3,
                            'title' => 'Analysis of Legal Issues',
                            'word_count' => 6500,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '3.1', 'title' => 'Examination of Subject Matter', 'word_count' => 2000, 'is_required' => true],
                                ['number' => '3.2', 'title' => 'Case Law Analysis', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '3.3', 'title' => 'Statutory Analysis', 'word_count' => 1500, 'is_required' => true],
                                ['number' => '3.4', 'title' => 'Critical Arguments', 'word_count' => 1500, 'is_required' => true],
                            ],
                        ],
                        [
                            'number' => 4,
                            'title' => 'Summary, Findings, and Recommendations',
                            'word_count' => 2000,
                            'completion_threshold' => 80,
                            'is_required' => true,
                            'sections' => [
                                ['number' => '4.1', 'title' => 'Summary of Major Arguments', 'word_count' => 1200, 'is_required' => true],
                                ['number' => '4.2', 'title' => 'Findings', 'word_count' => 1200, 'is_required' => true],
                                ['number' => '4.3', 'title' => 'Recommendations', 'word_count' => 1200, 'is_required' => true],
                                ['number' => '4.4', 'title' => 'Conclusion', 'word_count' => 600, 'is_required' => true],
                            ],
                        ],
                    ],
                ],
                'appendices' => [
                    'references_bibliography' => ['title' => 'References / Bibliography', 'description' => 'NALT style, OSCOLA, or APA/Harvard depending on faculty', 'is_required' => true],
                    'statutes_regulations' => ['title' => 'Copies of Statutes or Regulations Analyzed', 'is_required' => false],
                    'court_judgments' => ['title' => 'Court Judgments or Excerpts', 'is_required' => false],
                    'questionnaires_interviews' => ['title' => 'Questionnaires/Interviews', 'description' => 'If empirical research', 'is_required' => false],
                ],
            ],
            'chapter_templates' => [
                'introduction' => [
                    'description' => 'Chapter One: General Introduction - Legal research foundation',
                    'sections' => [
                        ['number' => '1.1', 'title' => 'Background of the Study', 'word_count' => 700, 'is_required' => true, 'description' => 'Legal context and background of the research area'],
                        ['number' => '1.2', 'title' => 'Statement of the Problem', 'word_count' => 600, 'is_required' => true, 'description' => 'Clear articulation of the legal problem or issue'],
                        ['number' => '1.3', 'title' => 'Aim and Objectives of the Study', 'word_count' => 500, 'is_required' => true, 'description' => 'Legal research goals and specific objectives'],
                        ['number' => '1.4', 'title' => 'Research Questions', 'word_count' => 300, 'is_required' => false, 'description' => 'Specific legal questions to be addressed'],
                        ['number' => '1.5', 'title' => 'Significance of the Study', 'word_count' => 600, 'is_required' => true, 'description' => 'Legal and academic importance of the research'],
                        ['number' => '1.6', 'title' => 'Scope of the Study', 'word_count' => 500, 'is_required' => true, 'description' => 'Boundaries and limitations of the legal research'],
                        ['number' => '1.7', 'title' => 'Methodology', 'word_count' => 800, 'is_required' => true, 'description' => 'Doctrinal, Non-doctrinal, Comparative, Analytical approaches'],
                        ['number' => '1.8', 'title' => 'Literature Review', 'word_count' => 500, 'is_required' => false, 'description' => 'Brief overview (detailed review in Chapter 2)'],
                    ],
                ],
                'literature_review' => [
                    'description' => 'Chapter Two: Literature Review and Thematic Discussions - Legal foundations',
                    'sections' => [
                        ['number' => '2.1', 'title' => 'Review of Doctrines and Principles', 'word_count' => 2000, 'is_required' => true, 'description' => 'Detailed review of legal doctrines and principles'],
                        ['number' => '2.2', 'title' => 'Statutory Framework Analysis', 'word_count' => 1500, 'is_required' => true, 'description' => 'Analysis of relevant statutes and legislation'],
                        ['number' => '2.3', 'title' => 'Case Law Review', 'word_count' => 1500, 'is_required' => true, 'description' => 'Review of relevant court decisions and precedents'],
                        ['number' => '2.4', 'title' => 'Theoretical Frameworks', 'word_count' => 800, 'is_required' => true, 'description' => 'Natural Law, Positivism, Realism, etc.'],
                        ['number' => '2.5', 'title' => 'Comparative Legal Perspectives', 'word_count' => 1200, 'is_required' => false, 'description' => 'How other jurisdictions handle the issue'],
                    ],
                ],
                'legal_analysis' => [
                    'description' => 'Chapter Three: Analysis of Legal Issues - Core content analysis',
                    'sections' => [
                        ['number' => '3.1', 'title' => 'Examination of Subject Matter', 'word_count' => 2000, 'is_required' => true, 'description' => 'Detailed examination of the legal subject matter'],
                        ['number' => '3.2', 'title' => 'Case Law Analysis', 'word_count' => 1500, 'is_required' => true, 'description' => 'Decisions of courts, tribunals, and judicial reasoning'],
                        ['number' => '3.3', 'title' => 'Statutory Analysis', 'word_count' => 1500, 'is_required' => true, 'description' => 'Analysis of laws, constitutions, treaties, regulations'],
                        ['number' => '3.4', 'title' => 'Critical Arguments', 'word_count' => 1500, 'is_required' => true, 'description' => 'Strengths, weaknesses, contradictions in the law'],
                    ],
                ],
            ],
            'guidance_templates' => [
                'common' => [
                    'legal_research_methodology' => [
                        'title' => 'Legal Research Methodology',
                        'items' => [
                            'Choose appropriate legal research method (Doctrinal, Non-doctrinal, Comparative)',
                            'Use authoritative legal sources and databases',
                            'Follow proper legal citation format (NALT, OSCOLA, or faculty preference)',
                            'Analyze relevant statutes, regulations, and constitutional provisions',
                            'Examine case law and judicial precedents systematically',
                            'Consider jurisdictional differences and comparative perspectives',
                        ],
                    ],
                    'case_law_analysis' => [
                        'title' => 'Case Law Analysis and Legal Reasoning',
                        'items' => [
                            'Identify ratio decidendi (legal reasoning) vs obiter dicta (incidental remarks)',
                            'Analyze judicial reasoning and legal principles applied',
                            'Distinguish, follow, or overrule precedents appropriately',
                            'Examine dissenting judgments for alternative legal perspectives',
                            'Consider the hierarchical structure of courts and binding precedents',
                            'Evaluate the persuasive value of foreign jurisdictions',
                        ],
                    ],
                    'statutory_interpretation' => [
                        'title' => 'Statutory Analysis and Interpretation',
                        'items' => [
                            'Apply rules of statutory interpretation (literal, golden, mischief rules)',
                            'Examine legislative intent and parliamentary debates where relevant',
                            'Consider constitutional validity and compliance with fundamental rights',
                            'Analyze amendments, repeals, and legislative history',
                            'Compare provisions with similar statutes in other jurisdictions',
                            'Assess practical implementation and judicial interpretation',
                        ],
                    ],
                    'legal_argumentation' => [
                        'title' => 'Legal Argumentation and Critical Analysis',
                        'items' => [
                            'Construct logical legal arguments based on authority and precedent',
                            'Present balanced analysis with counterarguments and alternative views',
                            'Identify gaps, inconsistencies, and ambiguities in the law',
                            'Propose legal reforms and policy recommendations',
                            'Support arguments with authoritative legal sources',
                            'Maintain objectivity while presenting critical evaluation',
                        ],
                    ],
                    'legal_writing_standards' => [
                        'title' => 'Legal Academic Writing Standards',
                        'items' => [
                            'Use precise legal terminology and avoid ambiguity',
                            'Maintain formal academic tone appropriate for legal scholarship',
                            'Structure arguments logically with clear legal reasoning',
                            'Cite authorities accurately using prescribed citation format',
                            'Include comprehensive table of cases and statutes',
                            'Ensure proper attribution and avoid plagiarism',
                        ],
                    ],
                ],
            ],
            'terminology' => [
                'common' => [
                    'ratio_decidendi' => 'The legal reasoning or principle underlying a court decision',
                    'obiter_dicta' => 'Incidental remarks in a judgment not essential to the decision',
                    'precedent' => 'Legal principle established by earlier court decisions',
                    'stare_decisis' => 'Legal doctrine of adhering to precedent decisions',
                    'jurisprudence' => 'The theory and philosophy of law',
                    'statute' => 'Written law passed by legislative body',
                    'constitutional_law' => 'Body of law dealing with the constitution and government powers',
                    'common_law' => 'Judge-made law developed through court decisions',
                    'equity' => 'System of legal principles supplementing strict law',
                    'tort' => 'Civil wrong that causes harm or loss',
                    'jurisdiction' => 'Authority of court to hear and decide cases',
                    'due_process' => 'Fair treatment through the normal judicial system',
                    'ultra_vires' => 'Beyond the legal power or authority',
                    'natural_justice' => 'Fundamental principles of fair legal procedure',
                    'burden_of_proof' => 'Obligation to prove allegations or charges',
                    'prima_facie' => 'Evidence sufficient to establish a fact unless rebutted',
                ],
            ],
        ];
    }
}
