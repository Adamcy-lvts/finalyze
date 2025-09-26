<?php

namespace App\Services;

use App\Models\ChapterSection;
use App\Models\Project;
use App\Models\ProjectOutline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectOutlineService
{
    public function __construct(
        private AIContentGenerator $aiGenerator
    ) {}

    /**
     * Generate and store complete project outline based on topic and field
     */
    public function generateProjectOutline(Project $project): bool
    {
        try {
            DB::beginTransaction();

            // Delete existing outlines if any
            $project->outlines()->delete();

            // Generate outline using AI
            $outlineData = $this->generateOutlineData($project);

            // Store the outline in database
            foreach ($outlineData as $chapterData) {
                $outline = ProjectOutline::create([
                    'project_id' => $project->id,
                    'chapter_number' => $chapterData['chapter_number'],
                    'chapter_title' => $chapterData['title'],
                    'target_word_count' => $chapterData['target_word_count'],
                    'completion_threshold' => $chapterData['completion_threshold'] ?? 80,
                    'description' => $chapterData['description'],
                    'display_order' => $chapterData['chapter_number'],
                    'is_required' => $chapterData['is_required'] ?? true,
                ]);

                // Create sections for this chapter
                if (isset($chapterData['sections'])) {
                    foreach ($chapterData['sections'] as $sectionData) {
                        ChapterSection::create([
                            'project_outline_id' => $outline->id,
                            'section_number' => $sectionData['section_number'],
                            'section_title' => $sectionData['title'],
                            'section_description' => $sectionData['description'],
                            'target_word_count' => $sectionData['target_word_count'],
                            'display_order' => $sectionData['display_order'] ?? 0,
                            'is_required' => $sectionData['is_required'] ?? true,
                        ]);
                    }
                }
            }

            DB::commit();
            Log::info('Project outline generated successfully', ['project_id' => $project->id]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate project outline', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate outline data using AI
     */
    private function generateOutlineData(Project $project): array
    {
        $prompt = $this->buildOutlinePrompt($project);

        try {
            $aiResponse = $this->aiGenerator->generate($prompt);

            return $this->parseOutlineResponse($aiResponse, $project);
        } catch (\Exception $e) {
            Log::warning('AI outline generation failed, using fallback', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getFallbackOutline($project);
        }
    }

    /**
     * Build prompt for AI outline generation
     */
    private function buildOutlinePrompt(Project $project): string
    {
        $prompt = "You are an academic writing expert creating a detailed thesis outline.\n\n";

        $prompt .= "PROJECT DETAILS:\n";
        $prompt .= "Topic: {$project->topic}\n";
        $prompt .= "Field of Study: {$project->field_of_study}\n";
        $prompt .= "Academic Level: {$project->type}\n";
        $prompt .= "University: {$project->university}\n";
        $prompt .= "Course: {$project->course}\n\n";

        $prompt .= "TASK: Create a comprehensive thesis outline with detailed chapter and section structure.\n\n";

        $prompt .= "REQUIREMENTS:\n";
        $prompt .= '1. Create '.$this->getChapterCount($project)." chapters appropriate for {$project->type} level\n";
        $prompt .= "2. Each chapter should have 3-6 sections with specific purposes\n";
        $prompt .= "3. Provide realistic word count targets for each chapter and section\n";
        $prompt .= "4. Consider standard academic structure for {$project->field_of_study} field\n";
        $prompt .= "5. Make sections specific to the research topic\n\n";

        $prompt .= "RESPONSE FORMAT (JSON only):\n";
        $prompt .= "{\n";
        $prompt .= '  "chapters": ['."\n";
        $prompt .= "    {\n";
        $prompt .= '      "chapter_number": 1,'."\n";
        $prompt .= '      "title": "Introduction",'."\n";
        $prompt .= '      "description": "Chapter description",'."\n";
        $prompt .= '      "target_word_count": 2500,'."\n";
        $prompt .= '      "completion_threshold": 80,'."\n";
        $prompt .= '      "sections": ['."\n";
        $prompt .= "        {\n";
        $prompt .= '          "section_number": "1.1",'."\n";
        $prompt .= '          "title": "Background",'."\n";
        $prompt .= '          "description": "Section description",'."\n";
        $prompt .= '          "target_word_count": 500,'."\n";
        $prompt .= '          "display_order": 1'."\n";
        $prompt .= "        }\n";
        $prompt .= "      ]\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";

        $prompt .= 'Provide ONLY valid JSON response with no additional text.';

        return $prompt;
    }

    /**
     * Parse AI response to extract outline data
     */
    private function parseOutlineResponse(string $response, Project $project): array
    {
        // Clean up response to get just JSON
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            throw new \Exception('No valid JSON found in response');
        }

        $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: '.json_last_error_msg());
        }

        if (! isset($data['chapters']) || ! is_array($data['chapters'])) {
            throw new \Exception('Invalid outline structure');
        }

        return $data['chapters'];
    }

    /**
     * Get fallback outline structure if AI fails
     */
    private function getFallbackOutline(Project $project): array
    {
        $chapterCount = $this->getChapterCount($project);
        $chapters = [];

        $standardChapters = [
            1 => ['title' => 'Introduction', 'word_count' => 2500],
            2 => ['title' => 'Literature Review', 'word_count' => 3500],
            3 => ['title' => 'Methodology', 'word_count' => 3000],
            4 => ['title' => 'Results', 'word_count' => 3500],
            5 => ['title' => 'Discussion and Conclusion', 'word_count' => 2500],
            6 => ['title' => 'Recommendations', 'word_count' => 2000], // For Masters/PhD
        ];

        for ($i = 1; $i <= $chapterCount; $i++) {
            $chapterInfo = $standardChapters[$i] ?? ['title' => "Chapter {$i}", 'word_count' => 2500];

            $chapters[] = [
                'chapter_number' => $i,
                'title' => $chapterInfo['title'],
                'description' => "Standard {$chapterInfo['title']} chapter",
                'target_word_count' => $chapterInfo['word_count'],
                'completion_threshold' => 80,
                'sections' => $this->getStandardSections($i),
            ];
        }

        return $chapters;
    }

    /**
     * Get standard sections for a chapter
     */
    private function getStandardSections(int $chapterNumber): array
    {
        $sectionTemplates = [
            1 => [ // Introduction
                ['number' => '1.1', 'title' => 'Background', 'description' => 'Context and background information', 'words' => 600],
                ['number' => '1.2', 'title' => 'Problem Statement', 'description' => 'Clear statement of the research problem', 'words' => 400],
                ['number' => '1.3', 'title' => 'Research Objectives', 'description' => 'Main and specific objectives', 'words' => 300],
                ['number' => '1.4', 'title' => 'Research Questions', 'description' => 'Key research questions to be answered', 'words' => 200],
                ['number' => '1.5', 'title' => 'Significance of Study', 'description' => 'Importance and expected contributions', 'words' => 300],
                ['number' => '1.6', 'title' => 'Scope and Limitations', 'description' => 'Boundaries and constraints', 'words' => 300],
            ],
            2 => [ // Literature Review
                ['number' => '2.1', 'title' => 'Introduction', 'description' => 'Overview of literature review', 'words' => 400],
                ['number' => '2.2', 'title' => 'Theoretical Framework', 'description' => 'Underlying theories and concepts', 'words' => 800],
                ['number' => '2.3', 'title' => 'Related Studies', 'description' => 'Review of relevant research', 'words' => 1500],
                ['number' => '2.4', 'title' => 'Research Gaps', 'description' => 'Identified gaps in literature', 'words' => 400],
                ['number' => '2.5', 'title' => 'Conceptual Framework', 'description' => 'Proposed conceptual model', 'words' => 400],
            ],
            3 => [ // Methodology
                ['number' => '3.1', 'title' => 'Introduction', 'description' => 'Overview of methodology', 'words' => 300],
                ['number' => '3.2', 'title' => 'Research Design', 'description' => 'Overall research approach', 'words' => 500],
                ['number' => '3.3', 'title' => 'Data Collection', 'description' => 'Methods for gathering data', 'words' => 600],
                ['number' => '3.4', 'title' => 'Data Analysis', 'description' => 'Analytical methods and procedures', 'words' => 500],
                ['number' => '3.5', 'title' => 'Ethical Considerations', 'description' => 'Research ethics and approval', 'words' => 300],
                ['number' => '3.6', 'title' => 'Limitations', 'description' => 'Methodological limitations', 'words' => 200],
            ],
        ];

        $sections = $sectionTemplates[$chapterNumber] ?? [
            ['number' => "{$chapterNumber}.1", 'title' => 'Introduction', 'description' => 'Chapter introduction', 'words' => 500],
            ['number' => "{$chapterNumber}.2", 'title' => 'Main Content', 'description' => 'Primary chapter content', 'words' => 1000],
            ['number' => "{$chapterNumber}.3", 'title' => 'Summary', 'description' => 'Chapter summary', 'words' => 300],
        ];

        return array_map(function ($section, $index) {
            return [
                'section_number' => $section['number'],
                'title' => $section['title'],
                'description' => $section['description'],
                'target_word_count' => $section['words'],
                'display_order' => $index + 1,
                'is_required' => true,
            ];
        }, $sections, array_keys($sections));
    }

    /**
     * Get expected chapter count based on project type
     */
    private function getChapterCount(Project $project): int
    {
        return match ($project->type) {
            'undergraduate' => 5,
            'masters' => 6,
            'phd' => 8,
            default => 5
        };
    }

    /**
     * Update section progress based on chapter content
     */
    public function updateSectionProgress(Project $project, int $chapterNumber, string $content): void
    {
        $outline = $project->outlines()->where('chapter_number', $chapterNumber)->first();

        if (! $outline) {
            return;
        }

        $sections = $outline->sections()->orderBy('display_order')->get();

        if ($sections->isEmpty()) {
            return;
        }

        // If content is empty or very minimal, reset all section completion
        $wordCount = str_word_count(strip_tags($content));
        if ($wordCount < 50) {
            Log::info('Content is empty or minimal, resetting all sections to incomplete', [
                'chapter_number' => $chapterNumber,
                'word_count' => $wordCount,
            ]);

            foreach ($sections as $section) {
                $section->update([
                    'current_word_count' => 0,
                    'is_completed' => false,
                ]);
            }

            return;
        }

        // Analyze content to detect which sections are present
        $this->analyzeContentForSections($sections, $content);
    }

    /**
     * Analyze content to detect which sections are present and calculate word counts
     */
    private function analyzeContentForSections($sections, string $content): void
    {
        // Strip HTML tags and normalize content
        $plainContent = strip_tags($content);
        $totalWords = str_word_count($plainContent);
        $totalSections = $sections->where('is_required', true)->count();

        // Track which sections we've found
        $sectionPositions = [];
        $undetectedSections = [];

        // Look for each section in the content
        foreach ($sections as $section) {
            $sectionFound = $this->detectSectionInContent($plainContent, $section);

            if ($sectionFound) {
                $sectionPositions[] = [
                    'section' => $section,
                    'start_position' => $sectionFound['position'],
                    'word_count' => $sectionFound['word_count'],
                ];
            } else {
                $undetectedSections[] = $section;
            }
        }

        // Handle undetected sections more intelligently
        if (! empty($undetectedSections) && $totalWords > 0) {
            // If we have substantial content but can't detect all sections,
            // assume the content covers all sections and distribute proportionally
            if ($totalWords >= ($totalSections * 200)) { // At least 200 words per section average
                $avgWordsPerUndetectedSection = intval($totalWords / max($totalSections, 1));

                foreach ($undetectedSections as $section) {
                    $section->update([
                        'current_word_count' => $avgWordsPerUndetectedSection,
                    ]);
                    $section->updateCompletionStatus();

                    Log::info('Section marked complete via intelligent fallback', [
                        'section' => $section->section_number.' '.$section->section_title,
                        'estimated_words' => $avgWordsPerUndetectedSection,
                        'total_content_words' => $totalWords,
                    ]);
                }
            } else {
                // Mark undetected sections as not completed only if content is truly minimal
                foreach ($undetectedSections as $section) {
                    $section->update([
                        'current_word_count' => 0,
                        'is_completed' => false,
                    ]);
                }
            }
        }

        // Update found sections with their word counts
        foreach ($sectionPositions as $sectionData) {
            $sectionData['section']->update([
                'current_word_count' => $sectionData['word_count'],
            ]);

            $sectionData['section']->updateCompletionStatus();
        }

        Log::info('Section analysis completed', [
            'found_sections' => count($sectionPositions),
            'total_sections' => $sections->count(),
        ]);
    }

    /**
     * Detect if a specific section exists in the content
     */
    private function detectSectionInContent(string $content, $section): array|false
    {
        $sectionNumber = preg_quote($section->section_number, '/');
        $sectionTitle = preg_quote($section->section_title, '/');

        // Multiple patterns to match section headings - more comprehensive patterns
        $patterns = [
            // Pattern 1: "3.1 Introduction" or "3.1. Introduction" (anywhere in line)
            "/{$sectionNumber}\.?\s+{$sectionTitle}/i",
            // Pattern 2: Just the section number followed by space (more flexible)
            "/{$sectionNumber}\.?\s+\w+/",
            // Pattern 3: Section title as heading (standalone)
            "/^{$sectionTitle}$/im",
            // Pattern 4: Section title after any whitespace
            "/\s{$sectionTitle}/i",
            // Pattern 5: Section title with markdown heading
            "/#+\s*{$sectionTitle}/i",
            // Pattern 6: Section number in various formats
            "/\b{$sectionNumber}\b/",
            // Pattern 7: More flexible title matching (partial words)
            "/{$sectionTitle}/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];

                // Calculate word count for this section
                $wordCount = $this->calculateSectionWordCount($content, $position, $section->section_number);

                Log::info('Section detected', [
                    'section' => $section->section_number.' '.$section->section_title,
                    'pattern' => $pattern,
                    'position' => $position,
                    'word_count' => $wordCount,
                ]);

                return [
                    'position' => $position,
                    'word_count' => $wordCount,
                    'pattern_matched' => $pattern,
                ];
            }
        }

        // Fallback: If no section headings found but content exists,
        // assume sections exist and distribute word count proportionally
        if (strlen(trim($content)) > 100) {
            Log::info('Section fallback applied - content exists but no clear section boundaries', [
                'section' => $section->section_number.' '.$section->section_title,
                'content_length' => strlen($content),
            ]);

            // Use proportional word count based on target
            $totalWords = str_word_count(strip_tags($content));
            $totalSections = $section->outline->sections()->where('is_required', true)->count();
            $estimatedSectionWords = $totalSections > 0 ? intval($totalWords / $totalSections) : $totalWords;

            return [
                'position' => 0,
                'word_count' => $estimatedSectionWords,
                'pattern_matched' => 'fallback_proportional',
            ];
        }

        return false;
    }

    /**
     * Calculate word count for a specific section
     */
    private function calculateSectionWordCount(string $content, int $startPosition, string $sectionNumber): int
    {
        // Find the next section or end of content
        $nextSectionPattern = "/^(\d+\.\d+\.?\s)/m";
        preg_match_all($nextSectionPattern, $content, $matches, PREG_OFFSET_CAPTURE, $startPosition + 1);

        if (! empty($matches[0])) {
            // Next section found, count words until then
            $endPosition = $matches[0][0][1];
            $sectionContent = substr($content, $startPosition, $endPosition - $startPosition);
        } else {
            // No next section, count to end of content
            $sectionContent = substr($content, $startPosition);
        }

        // Count words in this section
        $wordCount = str_word_count($sectionContent);

        // Minimum word count for very short sections
        return max($wordCount, 10);
    }
}
