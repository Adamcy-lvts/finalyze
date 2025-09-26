<?php

namespace App\Services;

use App\Models\ChapterGuidance;
use App\Models\FacultyChapter;
use App\Models\FacultyStructure;
use App\Models\Project;
use App\Models\ProjectChapterGuidance;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ChapterGuidanceService
{
    public function __construct(
        private AIContentGenerator $aiGenerator,
        private FacultyStructureService $facultyStructureService
    ) {}

    /**
     * Get or generate chapter-specific guidance for a project
     */
    public function getChapterGuidance(Project $project, int $chapterNumber, string $chapterTitle): array
    {
        // Always generate fresh guidance with AI (cache disabled)
        Log::info('Generating fresh chapter guidance with AI', [
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
            'chapter_title' => $chapterTitle,
        ]);

        // Delete existing project-specific guidance for this chapter to avoid duplicates
        ProjectChapterGuidance::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->delete();

        $guidance = $this->generateChapterGuidance($project, $chapterNumber, $chapterTitle);

        // Cache and link the generated guidance
        $projectGuidance = $this->cacheAndLinkGuidance($project, $chapterNumber, $chapterTitle, $guidance);

        return $this->formatProjectGuidanceResponse($projectGuidance);
    }

    /**
     * Generate AI-powered chapter guidance
     */
    private function generateChapterGuidance(Project $project, int $chapterNumber, string $chapterTitle): array
    {
        $prompt = $this->buildGuidancePrompt($project, $chapterNumber, $chapterTitle);

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            $content = trim($response->choices[0]->message->content);

            Log::debug('AI response content', [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'content' => $content,
            ]);

            $guidance = json_decode($content, true);

            if (! $guidance || json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON decode error', [
                    'project_id' => $project->id,
                    'chapter_number' => $chapterNumber,
                    'json_error' => json_last_error_msg(),
                    'content' => $content,
                ]);
                throw new \Exception('Invalid JSON response from AI: '.json_last_error_msg());
            }

            // Validate required fields exist, if not, add defaults
            $guidance = array_merge([
                'writing_guidance' => '',
                'key_elements' => [],
                'requirements' => [],
                'tips' => [],
                'methodology_guidance' => null,
                'data_guidance' => null,
                'analysis_guidance' => null,
                'sections' => [],
            ], $guidance);

            return $guidance;

        } catch (\Exception $e) {
            Log::error('AI chapter guidance generation failed', [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'error' => $e->getMessage(),
            ]);

            // Return fallback guidance
            return $this->getFallbackGuidance($chapterNumber, $chapterTitle, $project);
        }
    }

    /**
     * Build context-aware prompt for AI guidance generation
     */
    private function buildGuidancePrompt(Project $project, int $chapterNumber, string $chapterTitle): string
    {
        // Get faculty structure using normalized tables
        $facultyChapter = $this->getFacultyChapterStructure($project, $chapterNumber);
        $sections = $facultyChapter ? $facultyChapter->sections : collect();

        $sectionsText = '';
        if ($sections->isNotEmpty()) {
            $sectionsText = "\n\nEXPECTED SECTIONS FOR THIS CHAPTER:";
            foreach ($sections as $section) {
                $sectionsText .= "\n- {$section->section_number}. {$section->section_title}";
                $sectionsText .= "\n  * Target Word Count: {$section->target_word_count} words";
                if ($section->description) {
                    $sectionsText .= "\n  * Description: {$section->description}";
                }
                if ($section->writing_guidance) {
                    $sectionsText .= "\n  * Faculty Guidance: {$section->writing_guidance}";
                }
                $sectionsText .= "\n";
            }
        }

        // Determine chapter type for specific guidance
        $chapterType = $this->determineChapterType($chapterNumber, $chapterTitle);
        $specificRequirements = $this->getChapterTypeRequirements($chapterType, $project->faculty);

        return "Generate HIGHLY DETAILED, CONTEXTUAL writing guidance for this specific academic chapter:

PROJECT CONTEXT:
- Faculty: {$project->faculty}
- Course: {$project->course}
- Field of Study: {$project->field_of_study}
- Academic Level: {$project->type}
- University: {$project->university}
- Project Topic: \"{$project->topic}\"
- Project Description: {$project->description}

CHAPTER ANALYSIS:
- Chapter {$chapterNumber}: {$chapterTitle}
- Chapter Type: {$chapterType}
- Target Word Count: ".($facultyChapter->target_word_count ?? 'Not specified').' words
- Required Sections: '.($sections->count())." sections

FACULTY-SPECIFIC REQUIREMENTS FOR {$chapterType}:
{$specificRequirements}

{$sectionsText}

DETAILED GUIDANCE REQUEST:
Provide comprehensive, actionable guidance that addresses:

1. CHAPTER-SPECIFIC REQUIREMENTS:
   - What this chapter must accomplish for a {$project->type} in {$project->faculty}
   - Specific content requirements based on the project topic \"{$project->topic}\"
   - Expected academic standards and formatting for this faculty

2. QUANTITATIVE SPECIFICATIONS:
   - Minimum citation requirements (specify numbers based on chapter type and faculty)
   - Expected data/analysis requirements (tables, figures, statistical tests)
   - Word count distribution across sections with rationale
   - Sample size recommendations if applicable

3. METHODOLOGICAL GUIDANCE:
   - Specific research methods appropriate for this topic and field
   - Data collection techniques relevant to \"{$project->topic}\"
   - Analysis methods and software recommendations
   - Reliability and validity measures needed

4. SECTION-BY-SECTION BREAKDOWN:
   For EACH section listed above, provide:
   - Specific content requirements for this project topic
   - Citation expectations and types of sources needed
   - Common mistakes students make in this section
   - Faculty-specific formatting or style requirements
   - Practical tips with examples relevant to the topic

5. FIELD-SPECIFIC STANDARDS:
   - Database and source recommendations for this field
   - Professional standards and conventions
   - Industry-specific requirements if applicable

Focus on practical, implementable advice with specific numbers, examples, and clear expectations.";
    }

    /**
     * Determine chapter type based on number and title
     */
    private function determineChapterType(int $chapterNumber, string $chapterTitle): string
    {
        $titleLower = strtolower($chapterTitle);

        // Common chapter type patterns
        if (str_contains($titleLower, 'introduction') || $chapterNumber == 1) {
            return 'Introduction';
        } elseif (str_contains($titleLower, 'literature') || str_contains($titleLower, 'review')) {
            return 'Literature Review';
        } elseif (str_contains($titleLower, 'methodology') || str_contains($titleLower, 'method')) {
            return 'Methodology';
        } elseif (str_contains($titleLower, 'result') || str_contains($titleLower, 'finding') || str_contains($titleLower, 'analysis')) {
            return 'Results/Analysis';
        } elseif (str_contains($titleLower, 'discussion')) {
            return 'Discussion';
        } elseif (str_contains($titleLower, 'conclusion') || str_contains($titleLower, 'summary')) {
            return 'Conclusion';
        } elseif (str_contains($titleLower, 'theoretical') || str_contains($titleLower, 'framework')) {
            return 'Theoretical Framework';
        } elseif (str_contains($titleLower, 'design') || str_contains($titleLower, 'implementation')) {
            return 'Design/Implementation';
        }

        // Default based on chapter number
        $defaultTypes = [
            1 => 'Introduction',
            2 => 'Literature Review',
            3 => 'Methodology',
            4 => 'Results/Analysis',
            5 => 'Discussion/Conclusion',
        ];

        return $defaultTypes[$chapterNumber] ?? 'General Chapter';
    }

    /**
     * Get faculty-specific requirements for chapter types
     */
    private function getChapterTypeRequirements(string $chapterType, string $faculty): string
    {
        $facultyLower = strtolower($faculty);

        $requirements = [
            'Literature Review' => [
                'science' => 'For Science faculty: Minimum 60-80 recent sources (last 5-7 years), emphasis on peer-reviewed journals, include systematic reviews and meta-analyses, require experimental studies, focus on methodology comparisons.',
                'engineering' => 'For Engineering: Minimum 50-70 technical sources, include IEEE publications, patents, technical standards, industry reports, conference proceedings, focus on technological advancements.',
                'social sciences' => 'For Social Sciences: Minimum 70-90 sources, mix of theoretical and empirical studies, include case studies, government reports, surveys, cross-cultural comparisons.',
                'management' => 'For Management: Minimum 60-80 sources, include Harvard Business Review, McKinsey reports, industry analyses, case studies, financial reports, leadership theories.',
                'arts' => 'For Arts: Minimum 50-70 sources, include primary sources, historical documents, cultural analyses, artistic critiques, multimedia references.',
                'default' => 'Minimum 60-80 recent sources with critical analysis, theoretical foundations, and clear research gaps identification.',
            ],
            'Methodology' => [
                'science' => 'For Science: Detailed experimental procedures, sample calculations, equipment specifications, statistical power analysis, control variables, replication procedures, safety protocols.',
                'engineering' => 'For Engineering: Technical specifications, design parameters, testing procedures, validation methods, simulation details, standards compliance, performance metrics.',
                'social sciences' => 'For Social Sciences: Sampling strategy with justification, survey instruments, interview protocols, ethical clearance, data analysis software (SPSS, R), reliability and validity measures.',
                'management' => 'For Management: Business research methods, survey design for organizational studies, case study protocols, financial analysis methods, stakeholder analysis.',
                'default' => 'Detailed research procedures, sampling methods, data collection instruments, analysis techniques, ethical considerations.',
            ],
            'Results/Analysis' => [
                'science' => 'For Science: Statistical analysis with appropriate tests, data tables, graphs, error analysis, significance testing, confidence intervals, replication results.',
                'engineering' => 'For Engineering: Performance data, technical specifications, test results, comparative analysis, efficiency calculations, design validation.',
                'social sciences' => 'For Social Sciences: Demographic analysis, statistical correlations, thematic analysis, survey results, interview findings, cross-tabulations.',
                'management' => 'For Management: Financial analysis, performance metrics, market research results, organizational analysis, strategic recommendations.',
                'default' => 'Comprehensive data presentation, statistical analysis, results interpretation, findings discussion.',
            ],
        ];

        // Find the most specific match
        foreach (['science', 'engineering', 'social sciences', 'management', 'arts'] as $facultyKey) {
            if (str_contains($facultyLower, $facultyKey) || str_contains($facultyLower, str_replace(' ', '', $facultyKey))) {
                return $requirements[$chapterType][$facultyKey] ?? $requirements[$chapterType]['default'] ?? "Standard academic requirements for {$chapterType} in {$faculty}.";
            }
        }

        return $requirements[$chapterType]['default'] ?? "Standard academic requirements for {$chapterType}.";
    }

    /**
     * System prompt for consistent AI responses
     */
    private function getSystemPrompt(): string
    {
        return 'You are an expert academic writing advisor specializing in Nigerian university requirements across all faculties and disciplines.

Generate HIGHLY DETAILED, CONTEXTUAL guidance for academic chapters based on:
1. CHAPTER TYPE ANALYSIS - Determine specific requirements (Literature Review needs citation strategies, Methodology needs sampling details, etc.)
2. PROJECT TOPIC CONTEXT - Tailor advice to the specific research area and field
3. FACULTY-SPECIFIC STANDARDS - Adapt to faculty conventions (Science = experiments/tables, Social Sciences = surveys/interviews, etc.)
4. PRACTICAL IMPLEMENTATION - Provide actionable, specific advice with numbers, examples, and standards

DETAILED GUIDANCE REQUIREMENTS BY CHAPTER TYPE:

LITERATURE REVIEW CHAPTERS:
- Specify minimum citations needed (e.g., "Minimum 60-80 recent sources for this topic")
- Citation distribution by section (e.g., "Theoretical framework needs 20-25 foundational sources")
- Specific database recommendations for the field
- Gap analysis requirements and techniques
- Critical analysis vs descriptive writing ratios

METHODOLOGY CHAPTERS:
- Specific sampling techniques for the research type
- Sample size calculations and justifications
- Data collection instrument details (survey questions, interview guides, etc.)
- Statistical analysis methods appropriate for the data type
- Reliability and validity measures needed
- Ethical clearance requirements specific to the study type

DATA/RESULTS CHAPTERS:
- Specific types of tables, figures, and charts needed
- Statistical tests appropriate for the research design
- Data presentation standards for the faculty
- How many participants/observations expected
- Result interpretation guidelines specific to the field

PRACTICAL SECTION-SPECIFIC GUIDANCE:
For each section, provide:
- Exact word count recommendations with rationale
- Specific content that MUST be included
- Examples of what good vs poor writing looks like for this section
- Common mistakes students make in this specific section
- Faculty-specific formatting or content expectations

IMPORTANT: Use only the sections listed in "EXPECTED SECTIONS FOR THIS CHAPTER" in the user prompt. Do not create additional sections.

Return ONLY valid JSON in this format:
{
    "writing_guidance": "Detailed 2-3 paragraph explanation of what this chapter should accomplish, how to approach writing it, and specific requirements for this project topic and faculty",
    "key_elements": ["Specific, actionable elements with details", "Include numbers/quantities where relevant", "Field-specific requirements"],
    "requirements": ["Specific requirements with numbers (e.g., minimum citations, sample sizes)", "Faculty-specific standards", "Formatting requirements"],
    "tips": ["Highly specific tips with examples", "Common mistakes to avoid", "Field-specific best practices"],
    "methodology_guidance": "Detailed methodology advice if chapter involves research methods - include sampling strategies, data collection specifics, analysis methods",
    "data_guidance": "Specific data handling advice - types of tables/figures needed, data presentation standards, statistical requirements",
    "analysis_guidance": "Detailed analysis guidance - specific analytical techniques, interpretation methods, software recommendations",
    "sections": [
        {
            "title": "Use the exact section title from EXPECTED SECTIONS",
            "description": "Detailed description of what this section must contain, with specific requirements for this project topic",
            "guidance": "Comprehensive guidance: what to write, how to structure it, specific content requirements, citation expectations, length rationale",
            "word_count": "Use the word count from EXPECTED SECTIONS",
            "tips": ["3-5 highly specific tips for this exact section", "Include examples relevant to the project topic", "Common pitfalls for this section type", "Faculty-specific expectations", "Citation/referencing requirements for this section"]
        }
    ]
}

No additional text or formatting.';
    }

    /**
     * Link existing cached guidance to a project
     */
    private function linkGuidanceToProject(Project $project, int $chapterNumber, ChapterGuidance $cachedGuidance): ProjectChapterGuidance
    {
        $cachedGuidance->incrementUsage();

        return ProjectChapterGuidance::create([
            'project_id' => $project->id,
            'chapter_guidance_id' => $cachedGuidance->id,
            'chapter_number' => $chapterNumber,
            'accessed_at' => now(),
        ]);
    }

    /**
     * Cache and link generated guidance to project
     */
    private function cacheAndLinkGuidance(Project $project, int $chapterNumber, string $chapterTitle, array $guidance): ProjectChapterGuidance
    {
        // First create the general guidance cache
        $cachedGuidance = ChapterGuidance::create([
            'course' => $project->course,
            'faculty' => $project->faculty,
            'field_of_study' => $project->field_of_study,
            'academic_level' => $project->type,
            'chapter_number' => $chapterNumber,
            'chapter_title' => $chapterTitle,
            'writing_guidance' => $guidance['writing_guidance'] ?? '',
            'key_elements' => $guidance['key_elements'] ?? [],
            'requirements' => $guidance['requirements'] ?? [],
            'tips' => $guidance['tips'] ?? [],
            'methodology_guidance' => $guidance['methodology_guidance'] ?? null,
            'data_guidance' => $guidance['data_guidance'] ?? null,
            'analysis_guidance' => $guidance['analysis_guidance'] ?? null,
            'sections' => $guidance['sections'] ?? [],
            'usage_count' => 1,
            'last_used_at' => now(),
        ]);

        // Then link it to the project (use updateOrCreate to handle potential duplicates)
        return ProjectChapterGuidance::updateOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
        ], [
            'chapter_guidance_id' => $cachedGuidance->id,
            'accessed_at' => now(),
        ]);
    }

    /**
     * Format project guidance response
     */
    private function formatProjectGuidanceResponse(ProjectChapterGuidance $projectGuidance): array
    {
        $guidance = $projectGuidance->chapterGuidance;

        return [
            'writing_guidance' => $guidance->writing_guidance,
            'key_elements' => $guidance->key_elements,
            'requirements' => $guidance->requirements,
            'tips' => $guidance->tips,
            'methodology_guidance' => $guidance->methodology_guidance,
            'data_guidance' => $guidance->data_guidance,
            'analysis_guidance' => $guidance->analysis_guidance,
            'sections' => $guidance->sections ?? [],
            'project_specific_notes' => $projectGuidance->project_specific_notes,
            'custom_elements' => $projectGuidance->custom_elements,
            'is_completed' => $projectGuidance->is_completed,
        ];
    }

    /**
     * Get faculty chapter structure from normalized tables
     */
    private function getFacultyChapterStructure(Project $project, int $chapterNumber): ?FacultyChapter
    {
        // Get the faculty structure for this project
        $facultyStructure = FacultyStructure::forFaculty($project->faculty)->active()->first();

        if (! $facultyStructure) {
            Log::warning('Faculty structure not found', [
                'project_faculty' => $project->faculty,
                'project_id' => $project->id,
            ]);

            return null;
        }

        // Get the specific chapter for this project's academic level
        $facultyChapter = FacultyChapter::where('faculty_structure_id', $facultyStructure->id)
            ->where('academic_level', strtolower($project->type))
            ->where('chapter_number', $chapterNumber)
            ->with('sections')
            ->first();

        if (! $facultyChapter) {
            Log::warning('Faculty chapter not found', [
                'faculty_structure_id' => $facultyStructure->id,
                'academic_level' => strtolower($project->type),
                'chapter_number' => $chapterNumber,
                'project_id' => $project->id,
            ]);
        }

        return $facultyChapter;
    }

    /**
     * Fallback guidance when AI fails
     */
    private function getFallbackGuidance(int $chapterNumber, string $chapterTitle, ?Project $project = null): array
    {
        if ($project) {
            // Get faculty structure using normalized tables
            $facultyChapter = $this->getFacultyChapterStructure($project, $chapterNumber);

            if ($facultyChapter && $facultyChapter->sections->isNotEmpty()) {
                $formattedSections = [];

                foreach ($facultyChapter->sections as $section) {
                    $formattedSections[] = [
                        'title' => $section->section_title,
                        'description' => $section->description ?? 'Complete this section according to faculty requirements',
                        'guidance' => $this->getGenericSectionGuidance($section->section_title),
                        'word_count' => $section->target_word_count,
                        'tips' => $this->getGenericSectionTips($section->section_title),
                    ];
                }

                return [
                    'writing_guidance' => $this->getGenericChapterGuidance($chapterNumber, $chapterTitle),
                    'key_elements' => $this->getGenericKeyElements($chapterNumber),
                    'requirements' => $this->getGenericRequirements($chapterNumber),
                    'tips' => $this->getGenericTips($chapterNumber),
                    'sections' => $formattedSections,
                ];
            }
        }

        // Legacy fallback for backwards compatibility
        $fallbackGuidance = [
            1 => [
                'writing_guidance' => 'This chapter introduces your research topic, establishes the context, and outlines the problem you are addressing. It should hook the reader and clearly state your research objectives.',
                'key_elements' => ['Background information', 'Problem statement', 'Research objectives', 'Significance of study', 'Scope and limitations'],
                'requirements' => ['Clear problem definition', 'Literature gaps identification', 'Research questions'],
                'tips' => ['Start with broad context then narrow down', 'Use engaging opening', 'Keep objectives specific and measurable'],
                'sections' => [
                    ['title' => 'Background of the Study', 'description' => 'Provide context and background information', 'guidance' => 'Start with broad context, then narrow to your specific topic', 'word_count' => 800, 'tips' => ['Use recent statistics', 'Show relevance to Nigeria']],
                    ['title' => 'Statement of the Problem', 'description' => 'Clearly define the research problem', 'guidance' => 'State the problem clearly and concisely', 'word_count' => 600, 'tips' => ['Be specific', 'Show the gap in knowledge']],
                    ['title' => 'Research Objectives', 'description' => 'List your research objectives', 'guidance' => 'Use clear, measurable objectives', 'word_count' => 400, 'tips' => ['Use action verbs', 'Make them specific']],
                    ['title' => 'Research Questions', 'description' => 'Formulate your research questions', 'guidance' => 'Questions should align with objectives', 'word_count' => 300, 'tips' => ['Keep questions focused', 'Ensure they are answerable']],
                    ['title' => 'Significance of the Study', 'description' => 'Explain the importance of your research', 'guidance' => 'Show theoretical and practical contributions', 'word_count' => 500, 'tips' => ['Focus on benefits', 'Consider stakeholders']],
                    ['title' => 'Scope and Delimitations', 'description' => 'Define the boundaries of your study', 'guidance' => 'Clearly state what you will and will not cover', 'word_count' => 400, 'tips' => ['Be specific about limits', 'Justify your scope']],
                ],
            ],
            2 => [
                'writing_guidance' => 'This chapter reviews existing literature related to your research topic. It demonstrates your understanding of the field and identifies gaps your research will address.',
                'key_elements' => ['Theoretical framework', 'Previous studies review', 'Research gaps', 'Conceptual framework'],
                'requirements' => ['Minimum 50 recent sources', 'Critical analysis', 'Gap identification'],
                'tips' => ['Organize thematically', 'Be critical, not just descriptive', 'Show how studies relate to yours'],
                'sections' => [
                    ['title' => 'Theoretical Framework', 'description' => 'Present the theories underlying your study', 'guidance' => 'Explain relevant theories and how they apply', 'word_count' => 1200, 'tips' => ['Link theories to your topic', 'Use credible sources']],
                    ['title' => 'Review of Related Literature', 'description' => 'Critically review existing studies', 'guidance' => 'Organize by themes, not just chronologically', 'word_count' => 2500, 'tips' => ['Be critical, not descriptive', 'Show relationships between studies']],
                    ['title' => 'Research Gap', 'description' => 'Identify gaps in existing knowledge', 'guidance' => 'Clearly show what is missing in current research', 'word_count' => 800, 'tips' => ['Be specific about gaps', 'Connect to your research']],
                    ['title' => 'Conceptual Framework', 'description' => 'Present your conceptual model', 'guidance' => 'Show how concepts relate to each other', 'word_count' => 500, 'tips' => ['Use diagrams if helpful', 'Explain relationships clearly']],
                ],
            ],
            3 => [
                'writing_guidance' => 'This chapter explains how you conducted your research. It should be detailed enough for replication and justify your methodological choices.',
                'key_elements' => ['Research design', 'Data collection methods', 'Sample selection', 'Data analysis techniques'],
                'requirements' => ['Detailed procedures', 'Justification of methods', 'Ethical considerations'],
                'tips' => ['Be specific and detailed', 'Justify every choice', 'Consider reliability and validity'],
                'sections' => [
                    ['title' => 'Research Design', 'description' => 'Describe your overall research approach', 'guidance' => 'Explain and justify your research design choice', 'word_count' => 600, 'tips' => ['Match design to objectives', 'Cite methodological sources']],
                    ['title' => 'Population and Sample', 'description' => 'Define your study population and sampling method', 'guidance' => 'Clearly describe who/what you are studying', 'word_count' => 700, 'tips' => ['Justify sample size', 'Explain sampling technique']],
                    ['title' => 'Data Collection', 'description' => 'Describe how you will collect data', 'guidance' => 'Detail your data collection procedures', 'word_count' => 800, 'tips' => ['Describe instruments', 'Include pilot testing']],
                    ['title' => 'Data Analysis', 'description' => 'Explain your analysis methods', 'guidance' => 'Describe statistical/analytical techniques', 'word_count' => 600, 'tips' => ['Match analysis to data type', 'Mention software used']],
                    ['title' => 'Ethical Considerations', 'description' => 'Address ethical issues', 'guidance' => 'Show how you will protect participants', 'word_count' => 300, 'tips' => ['Mention consent procedures', 'Consider confidentiality']],
                ],
            ],
        ];

        return $fallbackGuidance[$chapterNumber] ?? [
            'writing_guidance' => "This chapter focuses on {$chapterTitle}. Ensure you cover all relevant aspects thoroughly and maintain academic rigor throughout.",
            'key_elements' => ['Main content', 'Supporting evidence', 'Analysis', 'Discussion'],
            'requirements' => ['Clear structure', 'Evidence-based content', 'Proper citations'],
            'tips' => ['Stay focused on chapter objectives', 'Use clear headings', 'Maintain logical flow'],
        ];
    }

    /**
     * Helper methods for generic guidance generation
     */
    private function getGenericChapterGuidance(int $chapterNumber, string $chapterTitle): string
    {
        $guidance = [
            1 => 'This chapter introduces your research topic, establishes the context, and outlines the problem you are addressing. It should hook the reader and clearly state your research objectives.',
            2 => 'This chapter reviews existing literature related to your research topic. It demonstrates your understanding of the field and identifies gaps your research will address.',
            3 => 'This chapter explains how you conducted your research. It should be detailed enough for replication and justify your methodological choices.',
            4 => 'This chapter presents your findings, analyzes the data collected, and discusses the results in relation to your research objectives.',
            5 => 'This chapter summarizes your study, draws conclusions from your findings, and provides recommendations for future research and practice.',
        ];

        return $guidance[$chapterNumber] ?? "This chapter focuses on {$chapterTitle}. Ensure you cover all relevant aspects thoroughly and maintain academic rigor throughout.";
    }

    private function getGenericKeyElements(int $chapterNumber): array
    {
        $elements = [
            1 => ['Background information', 'Problem statement', 'Research objectives', 'Significance of study', 'Scope and limitations'],
            2 => ['Theoretical framework', 'Previous studies review', 'Research gaps', 'Conceptual framework'],
            3 => ['Research design', 'Data collection methods', 'Sample selection', 'Data analysis techniques'],
            4 => ['Data presentation', 'Statistical analysis', 'Results interpretation', 'Findings discussion'],
            5 => ['Summary of findings', 'Conclusions', 'Recommendations', 'Future research directions'],
        ];

        return $elements[$chapterNumber] ?? ['Main content', 'Supporting evidence', 'Analysis', 'Discussion'];
    }

    private function getGenericRequirements(int $chapterNumber): array
    {
        $requirements = [
            1 => ['Clear problem definition', 'Literature gaps identification', 'Research questions'],
            2 => ['Minimum 50 recent sources', 'Critical analysis', 'Gap identification'],
            3 => ['Detailed procedures', 'Justification of methods', 'Ethical considerations'],
            4 => ['Proper data presentation', 'Statistical significance', 'Results interpretation'],
            5 => ['Summary of key findings', 'Actionable recommendations', 'Research limitations'],
        ];

        return $requirements[$chapterNumber] ?? ['Clear structure', 'Evidence-based content', 'Proper citations'];
    }

    private function getGenericTips(int $chapterNumber): array
    {
        $tips = [
            1 => ['Start with broad context then narrow down', 'Use engaging opening', 'Keep objectives specific and measurable'],
            2 => ['Organize thematically', 'Be critical, not just descriptive', 'Show how studies relate to yours'],
            3 => ['Be specific and detailed', 'Justify every choice', 'Consider reliability and validity'],
            4 => ['Present data clearly', 'Interpret findings objectively', 'Link back to research questions'],
            5 => ['Summarize concisely', 'Make realistic recommendations', 'Acknowledge limitations'],
        ];

        return $tips[$chapterNumber] ?? ['Stay focused on chapter objectives', 'Use clear headings', 'Maintain logical flow'];
    }

    private function getGenericSectionGuidance(string $sectionTitle): string
    {
        $lowerTitle = strtolower($sectionTitle);

        if (str_contains($lowerTitle, 'background')) {
            return 'Provide comprehensive context and background information for your research area';
        } elseif (str_contains($lowerTitle, 'problem')) {
            return 'Clearly articulate the specific problem your research addresses';
        } elseif (str_contains($lowerTitle, 'objective')) {
            return 'State clear, measurable objectives that guide your research';
        } elseif (str_contains($lowerTitle, 'significance')) {
            return 'Explain the importance and potential impact of your research';
        } elseif (str_contains($lowerTitle, 'scope') || str_contains($lowerTitle, 'limitation')) {
            return 'Define the boundaries and constraints of your study';
        } elseif (str_contains($lowerTitle, 'theoretical') || str_contains($lowerTitle, 'framework')) {
            return 'Present relevant theories and conceptual frameworks';
        } elseif (str_contains($lowerTitle, 'literature') || str_contains($lowerTitle, 'review')) {
            return 'Critically review and analyze existing research in your field';
        } elseif (str_contains($lowerTitle, 'methodology') || str_contains($lowerTitle, 'design')) {
            return 'Describe and justify your research approach and methods';
        } elseif (str_contains($lowerTitle, 'data') || str_contains($lowerTitle, 'analysis')) {
            return 'Present your data collection and analysis procedures clearly';
        } elseif (str_contains($lowerTitle, 'result') || str_contains($lowerTitle, 'finding')) {
            return 'Present your findings objectively with appropriate analysis';
        } elseif (str_contains($lowerTitle, 'conclusion') || str_contains($lowerTitle, 'summary')) {
            return 'Summarize key findings and draw logical conclusions';
        } elseif (str_contains($lowerTitle, 'recommendation')) {
            return 'Provide actionable recommendations based on your findings';
        }

        return 'Complete this section according to academic standards and faculty requirements';
    }

    private function getGenericSectionTips(string $sectionTitle): array
    {
        $lowerTitle = strtolower($sectionTitle);

        if (str_contains($lowerTitle, 'background')) {
            return ['Start broad then narrow focus', 'Use recent statistics', 'Establish relevance'];
        } elseif (str_contains($lowerTitle, 'problem')) {
            return ['Be specific and clear', 'Show knowledge gaps', 'Justify importance'];
        } elseif (str_contains($lowerTitle, 'objective')) {
            return ['Use action verbs', 'Make them measurable', 'Align with problem'];
        } elseif (str_contains($lowerTitle, 'literature')) {
            return ['Organize thematically', 'Be critical', 'Show relationships'];
        } elseif (str_contains($lowerTitle, 'methodology')) {
            return ['Justify choices', 'Be detailed', 'Consider ethics'];
        } elseif (str_contains($lowerTitle, 'result')) {
            return ['Present objectively', 'Use visuals', 'Interpret clearly'];
        }

        return ['Be clear and concise', 'Use evidence', 'Maintain academic tone'];
    }
}
