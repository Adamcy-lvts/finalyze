<?php

namespace App\Http\Controllers;

use App\Enums\ChapterStatus;
use App\Http\Requests\Projects\CreateProjectRequest;
use App\Http\Requests\Projects\SaveWizardProgressRequest;
use App\Http\Requests\Projects\TopicSelectionRequest;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Services\PreliminaryPageTemplateService;
use App\Services\Projects\ProjectReadService;
use App\Services\Projects\ProjectTopicService;
use App\Services\Projects\ProjectWizardService;
use App\Services\Projects\ProjectWritingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectReadService $projectReadService,
        private ProjectTopicService $projectTopicService,
        private ProjectWizardService $projectWizardService,
        private ProjectWritingService $projectWritingService,
    ) {}

    public function index()
    {
        $projects = auth()->user()->projects()
            ->with(['chapters', 'category', 'outlines', 'universityRelation', 'facultyRelation', 'departmentRelation'])
            ->latest()
            ->get();

        // Ensure projects auto-complete when all chapters are done
        $projects->each->syncCompletionStatusIfNeeded();

        return Inertia::render('projects/Index', [
            'projects' => $projects->map(function ($project) {
                $totalChapters = $project->chapters->count();
                $completedChapters = $project->chapters->filter(function ($chapter) {
                    return in_array($chapter->status, [ChapterStatus::Completed, ChapterStatus::Approved], true);
                })->count();

                return [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'title' => $project->title,
                    'type' => $project->type,
                    'mode' => $project->mode,
                    'status' => $project->status,
                    'topic_status' => $project->topic_status,
                    'progress' => $project->getProgressPercentage(),
                    'created_at' => $project->created_at->toISOString(),
                    'is_active' => $project->is_active,
                    'current_chapter' => $project->current_chapter,
                    'total_chapters' => $totalChapters,
                    'completed_chapters' => $completedChapters,
                    'university' => $project->universityRelation?->name,
                    'full_university_name' => $project->full_university_name,
                    'faculty' => $project->faculty_name,
                    'department' => $project->department_name,
                ];
            }),
        ]);
    }

    /**
     * Enhanced Create Method with Better Resume Logic
     */
    public function create()
    {
        // Priority 1: Check for explicit resume project in session
        $resumeProjectId = session('resume_project');
        $resumeProject = null;

        if ($resumeProjectId) {
            $resumeProject = Project::where('id', $resumeProjectId)
                ->where('user_id', auth()->id())
                ->where('status', 'setup')
                ->first();

            // Clear the session after retrieving
            session()->forget('resume_project');
        }

        // Priority 2: Look for the most recent active setup project
        if (! $resumeProject) {
            $resumeProject = Project::where('user_id', auth()->id())
                ->where('status', 'setup')
                ->where('is_active', true) // Prioritize active setup
                ->latest('updated_at')
                ->first();
        }

        // Priority 3: Look for any incomplete setup project
        if (! $resumeProject) {
            $resumeProject = Project::where('user_id', auth()->id())
                ->where('status', 'setup')
                ->latest('updated_at')
                ->first();
        }

        // Log the resume state for debugging
        Log::info('Project create page accessed', [
            'user_id' => auth()->id(),
            'resume_project' => $resumeProject ? [
                'id' => $resumeProject->id,
                'stored_step' => $resumeProject->setup_step,
                'actual_step' => $resumeProject->getActualCurrentStep(),
                'has_data' => ! empty($resumeProject->setup_data),
                'setup_data' => $resumeProject->setup_data,
            ] : null,
        ]);

        $allCategories = ProjectCategory::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'description' => $category->description,
                    'academic_levels' => $category->academic_levels,
                    'default_chapter_count' => $category->default_chapter_count,
                    'target_word_count' => $category->target_word_count,
                    'target_duration' => $category->target_duration,
                ];
            });

        // Group categories by academic level
        $categoriesByLevel = [];
        foreach ($allCategories as $category) {
            foreach ($category['academic_levels'] as $level) {
                if (! isset($categoriesByLevel[$level])) {
                    $categoriesByLevel[$level] = [];
                }
                $categoriesByLevel[$level][] = $category;
            }
        }

        return Inertia::render('projects/Create', [
            'projectCategories' => $categoriesByLevel,
            'resumeProject' => $resumeProject ? [
                'id' => $resumeProject->id,
                'current_step' => $resumeProject->getActualCurrentStep(),
                'wizard_data' => $resumeProject->getStepBasedSetupData(), // Send full step-based structure
            ] : null,
        ]);
    }

    public function store(CreateProjectRequest $request)
    {
        $validated = $request->validated();

        // Get the selected project category
        $category = ProjectCategory::findOrFail($validated['project_category_id']);

        // Check if we have an existing setup project to finalize
        $allSetupProjects = auth()->user()->projects()
            ->where('status', 'setup')
            ->get();

        $existingProject = auth()->user()->projects()
            ->where('status', 'setup')
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        Log::info('PROJECT COMPLETION - Setup Analysis', [
            'user_id' => auth()->id(),
            'all_setup_projects' => $allSetupProjects->count(),
            'setup_projects_details' => $allSetupProjects->map(fn ($p) => [
                'id' => $p->id,
                'status' => $p->status,
                'is_active' => $p->is_active,
                'slug' => $p->slug,
                'updated_at' => $p->updated_at,
            ]),
            'existing_project_found' => $existingProject ? $existingProject->id : null,
        ]);

        if ($existingProject) {
            // Complete the existing project setup
            Log::info('PROJECT COMPLETION - Using Existing Project', [
                'project_id' => $existingProject->id,
                'before_status' => $existingProject->status,
                'before_slug' => $existingProject->slug,
            ]);

            $project = $existingProject;
            $project->completeSetup($validated);

            Log::info('PROJECT COMPLETION - After completeSetup', [
                'project_id' => $project->id,
                'after_status' => $project->fresh()->status,
                'after_slug' => $project->fresh()->slug,
            ]);
        } else {
            Log::warning('PROJECT COMPLETION - No Active Setup Project Found, Creating New', [
                'user_id' => auth()->id(),
                'all_projects_count' => auth()->user()->projects()->count(),
                'reason' => 'This should rarely happen in normal flow',
            ]);

            // Deactivate other projects
            auth()->user()->projects()->update(['is_active' => false]);

            // Create new project (fallback case)
            $project = auth()->user()->projects()->create([
                'project_category_id' => $validated['project_category_id'],
                'type' => $validated['type'],
                'degree' => $validated['degree'] ?? null,
                'degree_abbreviation' => $validated['degree_abbreviation'] ?? null,
                'university_id' => $validated['university_id'],
                'faculty_id' => $validated['faculty_id'],
                'department_id' => $validated['department_id'],
                'course' => $validated['course'],
                'field_of_study' => $validated['field_of_study'],
                'supervisor_name' => $validated['supervisor_name'],
                'mode' => $validated['mode'],
                'status' => 'setup',
                'topic_status' => 'topic_selection',
                'is_active' => true,
                'current_chapter' => 0,
                'settings' => [
                    'matric_number' => $validated['matric_number'] ?? null,
                    'academic_session' => $validated['academic_session'],
                    'ai_assistance_level' => $validated['ai_assistance_level'] ?? 'moderate',
                ],
            ]);

            // Create project metadata
            $project->metadata()->create([
                'academic_session' => $validated['academic_session'],
                'matriculation_number' => $validated['matric_number'] ?? null,
            ]);

            // Initialize chapters based on category template
            $this->initializeChapters($project, $category);

            // Complete the setup process - this clears setup_data and moves to topic selection
            $project->completeSetup($validated);
        }

        // For existing projects, metadata and chapters should already exist
        if (! $existingProject) {
            // Only create metadata and initialize chapters for new projects
            // (existing projects already have these)
        }

        // Redirect to topic selection for AI companion flow
        return redirect()->route('projects.topic-selection', $project->slug);
    }

    /**
     * Enhanced Save Wizard Progress with Deep Merge
     * Ensures no data is lost between saves
     */
    public function saveWizardProgress(SaveWizardProgressRequest $request)
    {
        try {
            $validated = $request->validated();

            // If data is empty array, convert to empty object for consistency
            if (empty($validated['data'])) {
                $validated['data'] = [];

                // Don't save if we don't have a project ID and no data
                if (! $validated['project_id']) {
                    if (! app()->isProduction()) {
                        Log::info('Skipping save - no project ID and no data to save');
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'No data to save',
                        'project_id' => null,
                        'setup_step' => $validated['step'],
                    ]);
                }
            }

            if (! app()->isProduction()) {
                Log::info('Saving wizard progress', [
                    'project_id' => $validated['project_id'],
                    'step' => $validated['step'],
                    'data_keys' => array_keys($validated['data']),
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for wizard progress', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Unexpected error in wizard progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
            ], 500);
        }

        $project = $this->projectWizardService->saveProgress($request->user(), $validated);

        return response()->json([
            'success' => true,
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'setup_step' => $project->setup_step,
            'saved_data' => $project->setup_data, // Return saved data for debugging
        ]);
    }

    /**
     * TOPIC SELECTION PAGE
     * Shows after wizard completion - user selects or generates project topic
     */
    // public function topicSelection(Project $project)
    // {
    //     // Ensure user owns the project
    //     abort_if($project->user_id !== auth()->id(), 403);

    //     // Load relationships
    //     $project->load(['universityRelation', 'facultyRelation', 'departmentRelation']);

    //     // Load previously generated topics for this project context
    //     $savedTopics = $this->getProjectGeneratedTopics($project);

    //     return Inertia::render('projects/TopicSelection', [
    //         'project' => [
    //             'id' => $project->id,
    //             'slug' => $project->slug,
    //             'title' => $project->title,
    //             'topic' => $project->topic,
    //             'description' => $project->description,
    //             'type' => $project->type,
    //             'status' => $project->status,
    //             'field_of_study' => $project->field_of_study,
    //             'university' => $project->universityRelation?->name,
    //             'full_university_name' => $project->full_university_name,
    //             'faculty' => $project->faculty_name,
    //             'department' => $project->department_name,
    //             'course' => $project->course,
    //         ],
    //         'savedTopics' => $savedTopics,
    //     ]);
    // }
    public function topicSelection(TopicSelectionRequest $request, Project $project)
    {
        $payload = $this->projectTopicService->topicSelectionPayload($project, $request);

        return Inertia::render('projects/TopicSelection', $payload);
    }

    /**
     * TOPIC APPROVAL PAGE
     * Shows when user has selected topic and needs supervisor approval
     * This maintains state until supervisor approves/rejects
     */
    public function topicApproval(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Load relationships
        $project->load(['universityRelation', 'facultyRelation', 'departmentRelation']);

        return Inertia::render('projects/TopicApproval', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'description' => $project->description,
                'type' => $project->type,
                'status' => $project->status,
                'field_of_study' => $project->field_of_study,
                'course' => $project->course,
                'university' => $project->universityRelation?->name,
                'full_university_name' => $project->full_university_name,
                'faculty' => $project->faculty_name,
                'department' => $project->department_name,
                'supervisor_name' => $project->supervisor_name,
            ],
        ]);
    }

    /**
     * PROJECT WRITING DASHBOARD
     * Main writing interface after topic approval
     * Redirects based on writing mode (auto vs manual)
     */
    public function writing(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        $payload = $this->projectWritingService->writingPayload($project);

        return Inertia::render('projects/Writing', $payload + [
            'estimatedChapters' => $this->getEstimatedChapters($project),
        ]);
    }

    /**
     * Manually mark a project as completed (visible action on dashboard)
     */
    public function complete(Project $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $project->loadMissing('chapters', 'facultyRelation.structure.chapters');

        $allChaptersComplete = $project->chaptersAreComplete();
        $requiredChapterCount = $project->getRequiredChapterCount();
        $completedChapterCount = $project->chapters->count();

        if (! $allChaptersComplete || ($requiredChapterCount > 0 && $completedChapterCount < $requiredChapterCount)) {
            $message = 'Cannot mark as completed. ';

            if (! $allChaptersComplete) {
                $message .= 'All chapters must be completed/approved. ';
            }

            if ($requiredChapterCount > 0 && $completedChapterCount < $requiredChapterCount) {
                $message .= "Required chapters: {$completedChapterCount} / {$requiredChapterCount}.";
            }

            return back()->with('message', $message);
        }

        $project->markAsCompleted();

        return back()->with('message', 'Project marked as completed.');
    }

    public function show(Project $project)
    {
        // Ensure user owns the project (simplified check for now)
        abort_if($project->user_id !== auth()->id(), 403);

        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'type' => $project->type,
                'status' => $project->status,
                'mode' => $project->mode,
                'current_chapter' => $project->current_chapter,
                'chapters' => $project->chapters->map(function ($chapter) {
                    return [
                        'id' => $chapter->id,
                        'slug' => $chapter->slug,
                        'number' => $chapter->chapter_number,
                        'title' => $chapter->title,
                        'status' => $chapter->status,
                        'word_count' => $chapter->word_count,
                        'target_word_count' => $chapter->target_word_count,
                        'progress' => $chapter->getProgressPercentage(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified project
     */
    public function edit(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Load relationships
        $project->load(['universityRelation', 'facultyRelation', 'departmentRelation']);

        return Inertia::render('projects/Edit', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'description' => $project->description,
                'type' => $project->type,
                'status' => $project->status,
                'field_of_study' => $project->field_of_study,
                'mode' => $project->mode,
                'university' => $project->universityRelation?->slug ?? $project->university,
                'full_university_name' => $project->full_university_name,
                'faculty' => $project->facultyRelation?->slug ?? $project->faculty,
                'department' => $project->department_name,
                'course' => $project->course,
                'supervisor_name' => $project->supervisor_name,
                'settings' => $project->settings ?? [],
                'dedication' => $project->dedication,
                'acknowledgements' => $project->acknowledgements,
                'abstract' => $project->abstract,
                'declaration' => $project->declaration,
                'certification' => $project->certification,
                'certification_signatories' => $project->certification_signatories ?? [],
                'tables' => $project->tables ?? [],
                'abbreviations' => $project->abbreviations ?? [],
                'created_at' => $project->created_at->toISOString(),
            ],
            'preliminary_templates' => app(PreliminaryPageTemplateService::class)->getAllTemplates(),
        ]);
    }

    /**
     * Update the specified project in storage
     */
    public function update(\App\Http\Requests\UpdateProjectRequest $request, Project $project)
    {
        // Authorization is handled in UpdateProjectRequest

        $validated = $request->validated();

        Log::info('Project update requested', [
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'validated_data' => $validated,
        ]);

        try {
            // Merge settings if provided (preserve existing settings)
            if (isset($validated['settings'])) {
                $validated['settings'] = array_merge(
                    $project->settings ?? [],
                    $validated['settings']
                );
            }

            $project->update($validated);

            Log::info('Project updated successfully', [
                'project_id' => $project->id,
                'updated_fields' => array_keys($validated),
            ]);

            return redirect()->route('projects.show', $project->slug)
                ->with('success', 'Project details updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update project', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update project. Please try again.'])
                ->withInput();
        }
    }

    public function setActive(Project $project)
    {
        // Ensure user owns the project (simplified check for now)
        abort_if($project->user_id !== auth()->id(), 403);

        // Deactivate all user's projects
        auth()->user()->projects()->update(['is_active' => false]);

        // Activate this project
        $project->update(['is_active' => true]);

        return back()->with('success', 'Project set as active');
    }

    public function destroy(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'project_ids' => 'required|array|min:1',
            'project_ids.*' => 'required|exists:projects,id',
        ]);

        // Get projects that belong to the authenticated user
        $projects = Project::whereIn('id', $validated['project_ids'])
            ->where('user_id', auth()->id())
            ->get();

        // Ensure all requested projects belong to the user
        if ($projects->count() !== count($validated['project_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'One or more projects do not belong to you or do not exist.',
            ], 403);
        }

        $deletedCount = $projects->count();

        // Delete the projects
        foreach ($projects as $project) {
            $project->delete();
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully deleted {$deletedCount} project(s).",
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * GO BACK TO WIZARD FROM TOPIC SELECTION
     * Allows users to modify their project setup after wizard completion
     */
    public function goBackToWizard(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Only allow going back if we're in topic selection or later
        abort_if($project->status === 'setup', 400, 'Already in wizard setup');

        // Reset project to wizard mode while preserving data
        $project->goBackToWizard();

        // Set session flag to bypass state middleware
        session(['explicit_navigation' => true, 'resume_project' => $project->id]);

        return redirect()->route('projects.create')
            ->with('success', 'Returned to project setup');
    }

    /**
     * GO BACK TO TOPIC SELECTION FROM TOPIC APPROVAL
     * Allows users to change their topic selection
     */
    public function goBackToTopicSelection(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Only allow going back if we're in topic approval or later
        abort_if($project->status === 'setup' || $project->topic_status === 'topic_selection', 400, 'Already in topic selection or earlier');

        // Reset to topic selection
        $project->goBackToTopicSelection();

        // Set session flag to bypass state middleware
        session(['explicit_navigation' => true]);

        return redirect()->route('projects.topic-selection', $project->slug)
            ->with('success', 'Returned to topic selection');
    }

    /**
     * GO BACK TO TOPIC APPROVAL FROM WRITING
     * Allows users to modify their topic approval
     */
    public function goBackToTopicApproval(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Only allow going back if we're in writing stage
        abort_if(! in_array($project->status, ['writing', 'completed']), 400, 'Not in writing stage');

        // Reset to topic approval
        $project->goBackToTopicApproval();

        // Set session flag to bypass state middleware
        session(['explicit_navigation' => true]);

        return redirect()->route('projects.topic-approval', $project->slug)
            ->with('success', 'Returned to topic approval');
    }

    private function initializeChapters($project, $category = null)
    {
        // Use category template if available, otherwise fall back to default
        if ($category && $category->chapter_structure) {
            $chapters = $category->chapter_structure;
        } else {
            // Default chapter structure (for backward compatibility)
            $chapters = [
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
            ];
        }

        foreach ($chapters as $number => $chapterData) {
            Chapter::create([
                'project_id' => $project->id,
                'chapter_number' => $number,
                'title' => $chapterData['title'],
                'target_word_count' => $chapterData['target_word_count'],
                'outline' => $chapterData['outline'],
                'status' => 'not_started',
                'word_count' => 0,
                'version' => 1,
            ]);
        }
    }

    /**
     * GET ESTIMATED CHAPTER COUNT
     * Returns number of chapters based on project type and category
     */
    private function getEstimatedChapters(Project $project): int
    {
        // Use category default if available
        if ($project->category && $project->category->default_chapter_count) {
            return $project->category->default_chapter_count;
        }

        // Fall back to type-based estimation
        return match ($project->type) {
            'undergraduate' => 5,
            'masters' => 6,
            'phd' => 8,
            default => 5
        };
    }

    /**
     * UPDATE PROJECT WRITING MODE
     * Switch between auto (AI assisted) and manual writing modes
     */
    public function updateMode(Request $request, Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'mode' => 'required|in:auto,manual',
        ]);

        Log::info('Project writing mode update requested', [
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'old_mode' => $project->mode,
            'new_mode' => $validated['mode'],
        ]);

        try {
            $project->update([
                'mode' => $validated['mode'],
            ]);

            Log::info('Project writing mode updated successfully', [
                'project_id' => $project->id,
                'new_mode' => $validated['mode'],
            ]);

            return back()->with([
                'success' => true,
                'mode' => $validated['mode'],
                'message' => 'Writing mode updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update project writing mode', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update writing mode',
            ], 500);
        }
    }

    /**
     * Show the bulk generation page
     */
    public function bulkGenerate(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Ensure project topic is approved before bulk generation
        $allowedStatuses = ['topic_approved', 'writing', 'review', 'completed'];
        abort_if(! in_array($project->status->value, $allowedStatuses), 400, 'Project topic must be approved before bulk generation');

        // Get project with necessary relationships
        $project->load(['chapters', 'category', 'universityRelation', 'facultyRelation', 'departmentRelation']);

        return Inertia::render('projects/BulkGeneration', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'type' => $project->type,
                'status' => $project->status,
                'mode' => $project->mode,
                'field_of_study' => $project->field_of_study,
                'university' => $project->universityRelation?->name,
                'faculty' => $project->faculty_name,
                'department' => $project->department_name,
                'full_university_name' => $project->full_university_name,
                'course' => $project->course,
                'chapters' => $project->chapters->map(function ($chapter) {
                    return [
                        'id' => $chapter->id,
                        'chapter_number' => $chapter->chapter_number,
                        'title' => $chapter->title,
                        'target_word_count' => $chapter->target_word_count,
                        'word_count' => $chapter->word_count,
                        'status' => $chapter->status,
                    ];
                }),
                'category' => $project->category,
            ],
        ]);
    }

    /**
     * Start bulk generation process
     */
    /**
     * Start bulk generation process
     */
    public function startBulkGeneration(Request $request, Project $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'resume' => 'nullable|boolean',
        ]);

        $resume = $validated['resume'] ?? false;

        // Check if there's already a pending/processing generation
        $existingGeneration = \App\Models\ProjectGeneration::where('project_id', $project->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingGeneration) {
            return response()->json([
                'message' => 'Generation already in progress',
                'generation_id' => $existingGeneration->id,
            ]);
        }

        // Create new generation record
        $generation = \App\Models\ProjectGeneration::create([
            'project_id' => $project->id,
            'status' => 'pending',
            'current_stage' => $resume ? 'resuming' : 'initializing',
            'progress' => $resume ? ($project->getLatestGenerationProgress() ?? 0) : 0,
            'message' => $resume ? 'Resuming generation process...' : 'Initializing generation process...',
        ]);

        // Dispatch job
        \App\Jobs\BulkGenerateProject::dispatch($generation, $resume);

        return response()->json([
            'message' => 'Generation started',
            'generation_id' => $generation->id,
        ]);
    }

    /**
     * Check bulk generation status
     */
    public function checkBulkGenerationStatus(Project $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $generation = \App\Models\ProjectGeneration::where('project_id', $project->id)
            ->latest()
            ->first();

        if (! $generation) {
            return response()->json(['status' => 'not_started']);
        }

        // Load chapter statuses for better UI sync
        $chapterStatuses = $project->chapters()
            ->orderBy('chapter_number')
            ->get(['chapter_number', 'title', 'status', 'word_count', 'target_word_count'])
            ->map(function ($chapter) {
                return [
                    'chapter_number' => $chapter->chapter_number,
                    'title' => $chapter->title,
                    'status' => $chapter->status,
                    'word_count' => $chapter->word_count,
                    'target_word_count' => $chapter->target_word_count,
                    'is_completed' => $chapter->status === 'completed',
                ];
            });

        return response()->json([
            'status' => $generation->status,
            'current_stage' => $generation->current_stage,
            'progress' => $generation->progress,
            'message' => $generation->message,
            'details' => $generation->details,
            'metadata' => $generation->metadata,
            'chapter_statuses' => $chapterStatuses,
        ]);
    }

    /**
     * Cancel bulk generation
     */
    public function cancelBulkGeneration(Project $project)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $generation = \App\Models\ProjectGeneration::where('project_id', $project->id)
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();

        if ($generation) {
            $generation->update([
                'status' => 'cancelled',
                'message' => 'Generation cancelled by user',
            ]);
        }

        return response()->json(['message' => 'Generation cancelled']);
    }
}
