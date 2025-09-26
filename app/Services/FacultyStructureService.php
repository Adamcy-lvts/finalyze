<?php

namespace App\Services;

use App\Models\ChapterSection;
use App\Models\FacultyStructure;
use App\Models\Project;
use App\Models\ProjectOutline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FacultyStructureService
{
    /**
     * Get unified structure for a project based on faculty, course, and academic level
     */
    public function getProjectStructure(Project $project): array
    {
        $cacheKey = "faculty_structure_{$project->faculty}_{$project->type}";

        return Cache::remember($cacheKey, 3600, function () use ($project) {
            // Get faculty structure
            $facultyStructure = $this->getFacultyStructure($project->faculty);

            if (! $facultyStructure) {
                Log::warning('Faculty structure not found, using fallback', [
                    'project_id' => $project->id,
                    'faculty' => $project->faculty,
                ]);

                return $this->getFallbackStructure($project);
            }

            // Get structure for project's academic level
            $academicLevel = $this->determineAcademicLevel($project);
            $structure = $facultyStructure->getStructureForLevel($academicLevel);

            Log::info('Faculty structure retrieved', [
                'project_id' => $project->id,
                'faculty' => $project->faculty,
                'academic_level' => $academicLevel,
                'chapter_count' => count($structure['chapters']['default'] ?? []),
            ]);

            return $structure;
        });
    }

    /**
     * Get chapter structure for project - prioritize project outline, fallback to faculty structure
     */
    public function getChapterStructure(Project $project): array
    {
        // First check if project has custom outlines
        if ($project->outlines()->exists()) {
            return $this->getCustomChapterStructure($project);
        }

        // Fallback to faculty-based structure
        $structure = $this->getProjectStructure($project);
        $academicLevel = $this->determineAcademicLevel($project);

        return $structure['chapters']['default'] ?? $this->getFallbackChapterStructure($academicLevel);
    }

    /**
     * Get complete project structure including preliminary pages and appendices
     */
    public function getCompleteProjectStructure(Project $project): array
    {
        $structure = $this->getProjectStructure($project);

        return [
            'preliminary_pages' => $structure['preliminary_pages'] ?? [],
            'chapters' => $this->getChapterStructure($project),
            'appendices' => $structure['appendices'] ?? [],
        ];
    }

    /**
     * Get preliminary pages structure
     */
    public function getPreliminaryPages(Project $project): array
    {
        $structure = $this->getProjectStructure($project);

        return $structure['preliminary_pages'] ?? [];
    }

    /**
     * Get appendices structure
     */
    public function getAppendices(Project $project): array
    {
        $structure = $this->getProjectStructure($project);

        return $structure['appendices'] ?? [];
    }

    /**
     * Get guidance templates for project
     */
    public function getGuidanceTemplates(Project $project): array
    {
        $facultyStructure = $this->getFacultyStructure($project->faculty);
        $academicLevel = $this->determineAcademicLevel($project);

        if (! $facultyStructure) {
            return $this->getFallbackGuidanceTemplates($academicLevel);
        }

        return $facultyStructure->getGuidanceTemplates($academicLevel);
    }

    /**
     * Get faculty-specific terminology
     */
    public function getTerminology(Project $project): array
    {
        $facultyStructure = $this->getFacultyStructure($project->faculty);
        $academicLevel = $this->determineAcademicLevel($project);

        if (! $facultyStructure) {
            return [];
        }

        return $facultyStructure->getTerminology($academicLevel);
    }

    /**
     * Get project timeline recommendations
     */
    public function getTimelineRecommendations(Project $project): array
    {
        $facultyStructure = $this->getFacultyStructure($project->faculty);
        $academicLevel = $this->determineAcademicLevel($project);

        if (! $facultyStructure) {
            return $this->getFallbackTimeline($academicLevel);
        }

        return $facultyStructure->getEstimatedTimeline($academicLevel, $project->type);
    }

    /**
     * Create project outlines based on faculty structure
     */
    public function createProjectOutlines(Project $project): void
    {
        $chapterStructure = $this->getChapterStructure($project);

        foreach ($chapterStructure as $index => $chapterData) {
            $outline = ProjectOutline::create([
                'project_id' => $project->id,
                'chapter_number' => $chapterData['number'] ?? ($index + 1),
                'chapter_title' => $chapterData['title'],
                'target_word_count' => $chapterData['word_count'] ?? 3000,
                'completion_threshold' => $chapterData['completion_threshold'] ?? 80,
                'description' => $chapterData['description'] ?? '',
                'display_order' => $index + 1,
                'is_required' => $chapterData['is_required'] ?? true,
            ]);

            // Create chapter sections if defined
            if (isset($chapterData['sections'])) {
                $this->createChapterSections($outline, $chapterData['sections']);
            }
        }

        Log::info('Project outlines created from faculty structure', [
            'project_id' => $project->id,
            'faculty' => $project->faculty,
            'outline_count' => count($chapterStructure),
        ]);
    }

    /**
     * Get all available faculty structures
     */
    public function getAllFacultyStructures(): array
    {
        return Cache::remember('all_faculty_structures', 1800, function () {
            return FacultyStructure::active()
                ->orderBy('sort_order')
                ->orderBy('faculty_name')
                ->get()
                ->map(function ($structure) {
                    return [
                        'name' => $structure->faculty_name,
                        'slug' => $structure->faculty_slug,
                        'description' => $structure->description,
                        'academic_levels' => $structure->academic_levels,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Private helper methods
     */
    private function getFacultyStructure(string $facultyName): ?FacultyStructure
    {
        return FacultyStructure::active()
            ->forFaculty($facultyName)
            ->first();
    }

    private function determineAcademicLevel(Project $project): string
    {
        // Map project types to academic levels
        $typeMapping = [
            'undergraduate' => 'undergraduate',
            'bachelor' => 'undergraduate',
            'honors' => 'undergraduate',
            'masters' => 'masters',
            'msc' => 'masters',
            'ma' => 'masters',
            'mba' => 'masters',
            'phd' => 'phd',
            'doctorate' => 'phd',
        ];

        $projectType = strtolower($project->type);

        return $typeMapping[$projectType] ?? 'undergraduate';
    }

    private function getCustomChapterStructure(Project $project): array
    {
        return $project->outlines()
            ->orderBy('display_order')
            ->get()
            ->map(function ($outline) {
                return [
                    'number' => $outline->chapter_number,
                    'title' => $outline->chapter_title,
                    'word_count' => $outline->target_word_count,
                    'completion_threshold' => $outline->completion_threshold,
                    'description' => $outline->description,
                    'is_required' => $outline->is_required,
                    'sections' => $outline->sections->map(function ($section, $index) use ($outline) {
                        return [
                            'number' => $outline->chapter_number.'.'.($index + 1),
                            'title' => $section->section_title,
                            'description' => $section->section_description,
                            'word_count' => $section->target_word_count,
                            'is_required' => $section->is_required,
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();
    }

    private function createChapterSections(ProjectOutline $outline, array $sections): void
    {
        foreach ($sections as $index => $sectionData) {
            ChapterSection::create([
                'project_outline_id' => $outline->id,
                'section_number' => $sectionData['number'] ?? ($index + 1),
                'section_title' => $sectionData['title'],
                'section_description' => $sectionData['description'] ?? '',
                'target_word_count' => $sectionData['word_count'] ?? 500,
                'display_order' => $index + 1,
                'is_required' => $sectionData['is_required'] ?? true,
                'is_completed' => false,
                'current_word_count' => 0,
            ]);
        }
    }

    private function getFallbackStructure(Project $project): array
    {
        // Fallback to project category if available
        if ($project->category) {
            return [
                'chapters' => [
                    'default' => $project->category->chapter_structure ?? $this->getBasicChapterStructure(),
                ],
                'timeline' => [
                    'default' => [
                        'research_phase' => '2-3 months',
                        'writing_phase' => '3-4 months',
                        'review_phase' => '1-2 months',
                        'total_duration' => '6-9 months',
                    ],
                ],
            ];
        }

        return [
            'chapters' => ['default' => $this->getBasicChapterStructure()],
            'timeline' => [
                'default' => [
                    'research_phase' => '2-3 months',
                    'writing_phase' => '3-4 months',
                    'review_phase' => '1-2 months',
                    'total_duration' => '6-9 months',
                ],
            ],
        ];
    }

    private function getFallbackChapterStructure(string $academicLevel): array
    {
        return $this->getBasicChapterStructure();
    }

    private function getBasicChapterStructure(): array
    {
        return [
            ['number' => 1, 'title' => 'Introduction', 'word_count' => 3000, 'is_required' => true],
            ['number' => 2, 'title' => 'Literature Review', 'word_count' => 5000, 'is_required' => true],
            ['number' => 3, 'title' => 'Methodology', 'word_count' => 3000, 'is_required' => true],
            ['number' => 4, 'title' => 'Results and Analysis', 'word_count' => 4000, 'is_required' => true],
            ['number' => 5, 'title' => 'Conclusion and Recommendations', 'word_count' => 2000, 'is_required' => true],
        ];
    }

    private function getFallbackGuidanceTemplates(string $academicLevel): array
    {
        return [
            'research_phase' => [
                'title' => 'Research Phase Guidelines',
                'items' => [
                    'Define research questions and objectives',
                    'Conduct comprehensive literature review',
                    'Select appropriate research methodology',
                    'Prepare data collection instruments',
                ],
            ],
            'writing_phase' => [
                'title' => 'Writing Phase Guidelines',
                'items' => [
                    'Create detailed chapter outlines',
                    'Write first drafts of all chapters',
                    'Review and refine content',
                    'Ensure proper citation and referencing',
                ],
            ],
        ];
    }

    private function getFallbackTimeline(string $academicLevel): array
    {
        $timelines = [
            'undergraduate' => [
                'research_phase' => '1-2 months',
                'writing_phase' => '2-3 months',
                'review_phase' => '1 month',
                'total_duration' => '4-6 months',
            ],
            'masters' => [
                'research_phase' => '2-3 months',
                'writing_phase' => '3-4 months',
                'review_phase' => '1-2 months',
                'total_duration' => '6-9 months',
            ],
            'phd' => [
                'research_phase' => '6-12 months',
                'writing_phase' => '6-9 months',
                'review_phase' => '3-6 months',
                'total_duration' => '15-27 months',
            ],
        ];

        return $timelines[$academicLevel] ?? $timelines['undergraduate'];
    }
}
