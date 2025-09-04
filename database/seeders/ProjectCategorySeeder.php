<?php

namespace Database\Seeders;

use App\Models\ProjectCategory;
use Illuminate\Database\Seeder;

class ProjectCategorySeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Final Year Project',
                'slug' => 'final-year-project',
                'academic_levels' => ['undergraduate'],
                'description' => 'A comprehensive research project completed in the final year of undergraduate studies, typically involving original research and analysis.',
                'default_chapter_count' => 5,
                'chapter_structure' => [
                    1 => [
                        'title' => 'INTRODUCTION',
                        'target_word_count' => 2500,
                        'outline' => [
                            '1.1 Background to the Study',
                            '1.2 Statement of the Problem',
                            '1.3 Aim and Objectives of the Study',
                            '1.4 Research Questions',
                            '1.5 Research Hypotheses',
                            '1.6 Significance of the Study',
                            '1.7 Scope and Delimitation of the Study',
                            '1.8 Operational Definition of Terms',
                        ],
                    ],
                    2 => [
                        'title' => 'LITERATURE REVIEW',
                        'target_word_count' => 4000,
                        'outline' => [
                            '2.1 Conceptual Framework',
                            '2.2 Theoretical Framework',
                            '2.3 Empirical Review',
                            '2.4 Summary of Literature Review',
                            '2.5 Gap in Literature',
                        ],
                    ],
                    3 => [
                        'title' => 'RESEARCH METHODOLOGY',
                        'target_word_count' => 3000,
                        'outline' => [
                            '3.1 Research Design',
                            '3.2 Population of the Study',
                            '3.3 Sample Size and Sampling Technique',
                            '3.4 Research Instrument',
                            '3.5 Validity of the Instrument',
                            '3.6 Reliability of the Instrument',
                            '3.7 Method of Data Collection',
                            '3.8 Method of Data Analysis',
                        ],
                    ],
                    4 => [
                        'title' => 'DATA PRESENTATION, ANALYSIS AND INTERPRETATION',
                        'target_word_count' => 3500,
                        'outline' => [
                            '4.1 Data Presentation',
                            '4.2 Analysis of Research Questions',
                            '4.3 Testing of Hypotheses',
                            '4.4 Discussion of Findings',
                        ],
                    ],
                    5 => [
                        'title' => 'SUMMARY, CONCLUSION AND RECOMMENDATIONS',
                        'target_word_count' => 2000,
                        'outline' => [
                            '5.1 Summary',
                            '5.2 Conclusion',
                            '5.3 Recommendations',
                            '5.4 Contribution to Knowledge',
                            '5.5 Suggestions for Further Studies',
                        ],
                    ],
                ],
                'target_word_count' => 15000,
                'target_duration' => '2 semesters',
                'sort_order' => 1,
            ],
            [
                'name' => 'Seminar',
                'slug' => 'seminar',
                'academic_levels' => ['undergraduate'],
                'description' => 'A literature-based research paper that synthesizes existing knowledge on a specific topic without original empirical research.',
                'default_chapter_count' => 3,
                'chapter_structure' => [
                    1 => [
                        'title' => 'INTRODUCTION',
                        'target_word_count' => 2000,
                        'outline' => [
                            '1.1 Background to the Study',
                            '1.2 Statement of the Problem',
                            '1.3 Objectives of the Study',
                            '1.4 Research Questions',
                            '1.5 Significance of the Study',
                            '1.6 Scope of the Study',
                            '1.7 Definition of Terms',
                        ],
                    ],
                    2 => [
                        'title' => 'LITERATURE REVIEW AND DISCUSSION',
                        'target_word_count' => 5000,
                        'outline' => [
                            '2.1 Conceptual Framework',
                            '2.2 Theoretical Framework',
                            '2.3 Empirical Studies',
                            '2.4 Critical Analysis and Discussion',
                            '2.5 Synthesis of Literature',
                        ],
                    ],
                    3 => [
                        'title' => 'SUMMARY AND CONCLUSION',
                        'target_word_count' => 1500,
                        'outline' => [
                            '3.1 Summary of Findings',
                            '3.2 Conclusion',
                            '3.3 Recommendations',
                            '3.4 Suggestions for Further Research',
                        ],
                    ],
                ],
                'target_word_count' => 8500,
                'target_duration' => '1 semester',
                'sort_order' => 2,
            ],
            [
                'name' => 'Research Proposal',
                'slug' => 'research-proposal',
                'academic_levels' => ['undergraduate', 'postgraduate'],
                'description' => 'A detailed plan for a proposed research project, outlining the research problem, methodology, and expected outcomes.',
                'default_chapter_count' => 3,
                'chapter_structure' => [
                    1 => [
                        'title' => 'INTRODUCTION',
                        'target_word_count' => 2500,
                        'outline' => [
                            '1.1 Background to the Study',
                            '1.2 Statement of the Problem',
                            '1.3 Aim and Objectives of the Study',
                            '1.4 Research Questions',
                            '1.5 Research Hypotheses',
                            '1.6 Significance of the Study',
                            '1.7 Scope and Delimitation of the Study',
                            '1.8 Operational Definition of Terms',
                        ],
                    ],
                    2 => [
                        'title' => 'LITERATURE REVIEW',
                        'target_word_count' => 4000,
                        'outline' => [
                            '2.1 Conceptual Framework',
                            '2.2 Theoretical Framework',
                            '2.3 Empirical Review',
                            '2.4 Summary of Literature Review',
                            '2.5 Gap in Literature',
                        ],
                    ],
                    3 => [
                        'title' => 'RESEARCH METHODOLOGY',
                        'target_word_count' => 3500,
                        'outline' => [
                            '3.1 Research Design',
                            '3.2 Population of the Study',
                            '3.3 Sample Size and Sampling Technique',
                            '3.4 Research Instrument',
                            '3.5 Validity and Reliability of the Instrument',
                            '3.6 Method of Data Collection',
                            '3.7 Method of Data Analysis',
                            '3.8 Ethical Considerations',
                            '3.9 Timeline and Budget',
                        ],
                    ],
                ],
                'target_word_count' => 10000,
                'target_duration' => '1 semester',
                'sort_order' => 3,
            ],
            [
                'name' => 'Masters Thesis',
                'slug' => 'masters-thesis',
                'academic_levels' => ['postgraduate'],
                'description' => 'An advanced research project demonstrating mastery of a subject area and contributing new knowledge to the field.',
                'default_chapter_count' => 6,
                'chapter_structure' => [
                    1 => [
                        'title' => 'INTRODUCTION',
                        'target_word_count' => 3000,
                        'outline' => [
                            '1.1 Background to the Study',
                            '1.2 Statement of the Problem',
                            '1.3 Aim and Objectives of the Study',
                            '1.4 Research Questions',
                            '1.5 Research Hypotheses',
                            '1.6 Significance of the Study',
                            '1.7 Scope and Delimitation of the Study',
                            '1.8 Operational Definition of Terms',
                        ],
                    ],
                    2 => [
                        'title' => 'LITERATURE REVIEW',
                        'target_word_count' => 6000,
                        'outline' => [
                            '2.1 Conceptual Framework',
                            '2.2 Theoretical Framework',
                            '2.3 Empirical Review',
                            '2.4 Critical Analysis',
                            '2.5 Summary and Gap in Literature',
                        ],
                    ],
                    3 => [
                        'title' => 'RESEARCH METHODOLOGY',
                        'target_word_count' => 4000,
                        'outline' => [
                            '3.1 Research Philosophy',
                            '3.2 Research Design and Approach',
                            '3.3 Population and Sampling',
                            '3.4 Data Collection Methods',
                            '3.5 Data Analysis Techniques',
                            '3.6 Validity and Reliability',
                            '3.7 Ethical Considerations',
                        ],
                    ],
                    4 => [
                        'title' => 'DATA PRESENTATION AND ANALYSIS',
                        'target_word_count' => 5000,
                        'outline' => [
                            '4.1 Data Presentation',
                            '4.2 Descriptive Analysis',
                            '4.3 Inferential Analysis',
                            '4.4 Hypothesis Testing',
                            '4.5 Discussion of Findings',
                        ],
                    ],
                    5 => [
                        'title' => 'DISCUSSION OF FINDINGS',
                        'target_word_count' => 4000,
                        'outline' => [
                            '5.1 Overview of Findings',
                            '5.2 Discussion in Relation to Literature',
                            '5.3 Theoretical Implications',
                            '5.4 Practical Implications',
                            '5.5 Study Limitations',
                        ],
                    ],
                    6 => [
                        'title' => 'SUMMARY, CONCLUSION AND RECOMMENDATIONS',
                        'target_word_count' => 3000,
                        'outline' => [
                            '6.1 Summary of Study',
                            '6.2 Conclusions',
                            '6.3 Recommendations',
                            '6.4 Contribution to Knowledge',
                            '6.5 Suggestions for Further Research',
                        ],
                    ],
                ],
                'target_word_count' => 25000,
                'target_duration' => '3-4 semesters',
                'sort_order' => 4,
            ],
            [
                'name' => 'HND Project',
                'slug' => 'hnd-project',
                'academic_levels' => ['hnd'],
                'description' => 'A practical project combining theoretical knowledge with hands-on application in a professional context.',
                'default_chapter_count' => 5,
                'chapter_structure' => [
                    1 => [
                        'title' => 'INTRODUCTION',
                        'target_word_count' => 2000,
                        'outline' => [
                            '1.1 Background of the Study',
                            '1.2 Statement of Problem',
                            '1.3 Objectives of the Study',
                            '1.4 Significance of the Study',
                            '1.5 Scope and Limitation of the Study',
                            '1.6 Definition of Terms',
                        ],
                    ],
                    2 => [
                        'title' => 'LITERATURE REVIEW',
                        'target_word_count' => 3000,
                        'outline' => [
                            '2.1 Conceptual Review',
                            '2.2 Theoretical Framework',
                            '2.3 Related Studies',
                            '2.4 Summary of Literature',
                        ],
                    ],
                    3 => [
                        'title' => 'SYSTEM ANALYSIS AND DESIGN',
                        'target_word_count' => 3500,
                        'outline' => [
                            '3.1 Analysis of Existing System',
                            '3.2 Problems of Existing System',
                            '3.3 Design of New System',
                            '3.4 System Requirements',
                            '3.5 System Specifications',
                        ],
                    ],
                    4 => [
                        'title' => 'SYSTEM IMPLEMENTATION AND TESTING',
                        'target_word_count' => 3000,
                        'outline' => [
                            '4.1 System Implementation',
                            '4.2 System Testing',
                            '4.3 Documentation',
                            '4.4 Training and Changeover',
                        ],
                    ],
                    5 => [
                        'title' => 'SUMMARY, CONCLUSION AND RECOMMENDATIONS',
                        'target_word_count' => 1500,
                        'outline' => [
                            '5.1 Summary',
                            '5.2 Conclusion',
                            '5.3 Recommendations',
                            '5.4 Suggestions for Further Development',
                        ],
                    ],
                ],
                'target_word_count' => 13000,
                'target_duration' => '2 semesters',
                'sort_order' => 5,
            ],
        ];

        foreach ($categories as $category) {
            ProjectCategory::create($category);
        }
    }
}
