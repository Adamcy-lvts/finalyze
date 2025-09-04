<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = auth()->user()->projects()
            ->with('chapters')
            ->latest()
            ->get();

        return Inertia::render('projects/Index', [
            'projects' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'title' => $project->title,
                    'type' => $project->type,
                    'status' => $project->status,
                    'topic_status' => $project->topic_status,
                    'progress' => $project->getProgressPercentage(),
                    'created_at' => $project->created_at->toISOString(),
                    'is_active' => $project->is_active,
                    'current_chapter' => $project->current_chapter,
                    'university' => $project->university,
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_category_id' => 'required|exists:project_categories,id',
            'type' => 'required|in:undergraduate,postgraduate,hnd,nd',
            'university' => 'required|string',
            'faculty' => 'required|string',
            'department' => 'required|string',
            'course' => 'required|string',
            'field_of_study' => 'nullable|string',
            'supervisor_name' => 'nullable|string',
            'matric_number' => 'nullable|string',
            'academic_session' => 'required|string',
            'mode' => 'required|in:auto,manual',
            'ai_assistance_level' => 'nullable|in:minimal,moderate,maximum',
        ]);

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
                'university' => $validated['university'],
                'course' => $validated['course'],
                'field_of_study' => $validated['field_of_study'],
                'supervisor_name' => $validated['supervisor_name'],
                'mode' => $validated['mode'],
                'status' => 'topic_selection',
                'is_active' => true,
                'current_chapter' => 0,
                'settings' => [
                    'faculty' => $validated['faculty'],
                    'department' => $validated['department'],
                    'matric_number' => $validated['matric_number'] ?? null,
                    'academic_session' => $validated['academic_session'],
                    'ai_assistance_level' => $validated['ai_assistance_level'] ?? 'moderate',
                ],
            ]);

            // Create project metadata
            $project->metadata()->create([
                'academic_session' => $validated['academic_session'],
                'matriculation_number' => $validated['matric_number'] ?? null,
                'department' => $validated['department'],
                'faculty' => $validated['faculty'],
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
    public function saveWizardProgress(Request $request)
    {
        try {
            // Log incoming request for debugging
            Log::info('Wizard progress request received', [
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            $validated = $request->validate([
                'project_id' => 'nullable|exists:projects,id',
                'step' => 'required|integer|min:1|max:3',
                'data' => 'required|array',
            ]);

            // If data is empty array, convert to empty object for consistency
            if (empty($validated['data'])) {
                $validated['data'] = [];

                // Don't save if we don't have a project ID and no data
                if (! $validated['project_id']) {
                    Log::info('Skipping save - no project ID and no data to save');

                    return response()->json([
                        'success' => true,
                        'message' => 'No data to save',
                        'project_id' => null,
                        'setup_step' => $validated['step'],
                    ]);
                }
            }

            Log::info('Saving wizard progress', [
                'project_id' => $validated['project_id'],
                'step' => $validated['step'],
                'data' => $validated['data'],
            ]);
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

        if ($validated['project_id']) {
            // Update existing project progress using step-based approach
            $project = Project::where('id', $validated['project_id'])
                ->where('user_id', auth()->id())
                ->where('status', 'setup')
                ->firstOrFail();

            // Filter incoming data to remove null/undefined values
            $filteredStepData = array_filter($validated['data'], function ($value) {
                return $value !== null && $value !== '';
            });

            // Save using new step-based method
            $project->saveStepData($validated['step'], $filteredStepData);

            Log::info('Updated existing project with step-based data', [
                'project_id' => $project->id,
                'step' => $validated['step'],
                'step_data' => $filteredStepData,
                'step_based_structure' => $project->getStepBasedSetupData(),
            ]);

        } else {
            // CLEANUP: Ensure only one active setup project per user
            $this->cleanupSetupProjects();

            // BULLETPROOF: Try to find ANY existing setup project before creating new
            // Filter incoming data to remove null/undefined values
            $filteredStepData = array_filter($validated['data'], function ($value) {
                return $value !== null && $value !== '';
            });

            // Extract key fields for required project fields
            $projectType = $filteredStepData['projectType'] ?? 'undergraduate';
            $projectCategoryId = $filteredStepData['projectCategoryId'] ?? null;

            // Count existing projects before any action
            $existingProjectsCount = auth()->user()->projects()->count();
            $setupProjectsCount = auth()->user()->projects()->where('status', 'setup')->count();

            // SMART RESOLUTION: Try to find an existing setup project to reuse
            $existingSetupProject = auth()->user()->projects()
                ->where('status', 'setup')
                ->latest('updated_at') // Get the most recent
                ->first();

            if ($existingSetupProject) {
                // REUSE: Update the existing setup project instead of creating new
                Log::info('PROJECT WIZARD - Reusing Existing Setup Project', [
                    'user_id' => auth()->id(),
                    'step' => $validated['step'],
                    'reused_project_id' => $existingSetupProject->id,
                    'existing_projects_total' => $existingProjectsCount,
                    'reason' => 'Found existing setup project to reuse',
                ]);

                $project = $existingSetupProject;

                // Make sure this is the active setup project
                auth()->user()->projects()
                    ->where('status', 'setup')
                    ->where('id', '!=', $project->id)
                    ->update(['is_active' => false]);

                $project->update(['is_active' => true]);

            } else {
                // ONLY CREATE if no setup project exists at all
                Log::info('PROJECT WIZARD - Creating New Setup Project (No Existing Found)', [
                    'user_id' => auth()->id(),
                    'step' => $validated['step'],
                    'existing_projects_total' => $existingProjectsCount,
                    'existing_setup_projects' => $setupProjectsCount,
                    'project_type' => $projectType,
                    'incoming_data' => $filteredStepData,
                ]);

                // Deactivate other setup projects for this user
                auth()->user()->projects()
                    ->where('status', 'setup')
                    ->update(['is_active' => false]);

                // Create project with initial empty step-based data
                $project = auth()->user()->projects()->create([
                    'status' => 'setup',
                    'setup_step' => 1,
                    'setup_data' => [
                        'format_version' => '2.0',
                        'steps' => [],
                        'current_step' => 1,
                        'furthest_completed_step' => 0,
                    ],
                    'current_chapter' => 0,
                    'is_active' => true, // Make this the active setup project
                    'type' => $projectType,
                    'project_category_id' => $projectCategoryId,
                    // Use placeholder values that will be updated when setup completes
                    'field_of_study' => null,
                    'university' => 'TBD',
                    'course' => 'TBD',
                    'title' => 'Project Setup in Progress',
                ]);

                Log::info('PROJECT WIZARD - Created New Setup Project', [
                    'project_id' => $project->id,
                    'step' => $validated['step'],
                    'step_data' => $filteredStepData,
                    'status' => $project->status,
                    'is_active' => $project->is_active,
                    'slug' => $project->slug,
                    'total_projects_after' => auth()->user()->projects()->count(),
                    'step_based_structure' => $project->getStepBasedSetupData(),
                ]);
            }

            // Save step data regardless of whether we reused or created
            $project->saveStepData($validated['step'], $filteredStepData);

            Log::info('PROJECT WIZARD - Final Result', [
                'project_id' => $project->id,
                'step' => $validated['step'],
                'final_status' => $project->fresh()->status,
                'final_is_active' => $project->fresh()->is_active,
                'action_taken' => $existingSetupProject ? 'reused_existing' : 'created_new',
            ]);
        }

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
    public function topicSelection(Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        return Inertia::render('projects/TopicSelection', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'type' => $project->type,
                'status' => $project->status,
                'field_of_study' => $project->field_of_study,
                'university' => $project->university,
                'course' => $project->course,
            ],
        ]);
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

        // Load project with chapters and category for word count calculations
        $project->load(['chapters', 'category']);

        return Inertia::render('projects/Writing', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'type' => $project->type,
                'status' => $project->status,
                'mode' => $project->mode,
                'field_of_study' => $project->field_of_study,
                'university' => $project->university,
                'course' => $project->course,
                'chapters' => $project->chapters->map(function ($chapter) {
                    return [
                        'id' => $chapter->id,
                        'chapter_number' => $chapter->chapter_number,
                        'title' => $chapter->title,
                        'content' => $chapter->content,
                        'word_count' => $chapter->word_count,
                        'status' => $chapter->status,
                        'updated_at' => $chapter->updated_at->toISOString(),
                    ];
                }),
            ],
            'targetWordCount' => $project->category?->target_word_count ?? 15000,
            'estimatedChapters' => $this->getEstimatedChapters($project),
        ]);
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
        abort_if(in_array($project->status, ['setup', 'topic_selection']), 400, 'Already in topic selection or earlier');

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
     * CLEANUP: Ensure user has only one active setup project
     * This prevents duplicates at the application level
     */
    private function cleanupSetupProjects(): void
    {
        $setupProjects = auth()->user()->projects()
            ->where('status', 'setup')
            ->orderBy('updated_at', 'desc')
            ->get();

        if ($setupProjects->count() > 1) {
            Log::info('CLEANUP - Multiple setup projects found', [
                'user_id' => auth()->id(),
                'setup_projects_count' => $setupProjects->count(),
                'project_ids' => $setupProjects->pluck('id')->toArray(),
            ]);

            // Keep the most recent one active, deactivate the rest
            $keepProject = $setupProjects->first();
            $duplicateProjects = $setupProjects->skip(1);

            // Deactivate duplicates
            foreach ($duplicateProjects as $duplicate) {
                $duplicate->update(['is_active' => false]);
            }

            // Ensure the kept project is active
            $keepProject->update(['is_active' => true]);

            Log::info('CLEANUP - Cleaned up duplicate setup projects', [
                'kept_project_id' => $keepProject->id,
                'deactivated_count' => $duplicateProjects->count(),
            ]);
        }
    }
}
