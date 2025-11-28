<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectTopicStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'user_id', 'student_id', 'project_category_id',
        'university_id', 'faculty_id', 'department_id', // New foreign keys
        'title', 'slug', 'topic', 'description', 'type', 'degree', 'degree_abbreviation', 'status', 'topic_status',
        'mode', 'field_of_study', 'university', 'faculty', 'course', // Keep old string fields for backwards compatibility
        'supervisor_name', 'certification_signatories', 'current_chapter', 'is_active', 'settings',
        'setup_step', 'setup_data', 'last_activity_at',
        'paper_collection_status', 'paper_collection_message', 'paper_collection_count',
        'paper_collection_completed_at', 'citation_guaranteed',
        'dedication', 'acknowledgements', 'abstract', 'declaration', 'certification', 'references', 'appendices', 'tables', 'abbreviations',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'topic_status' => ProjectTopicStatus::class,
        'settings' => 'array',
        'setup_data' => 'array',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
        'paper_collection_completed_at' => 'datetime',
        'citation_guaranteed' => 'boolean',
        'certification_signatories' => 'array',
        'tables' => 'array',
        'abbreviations' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chapterGuidances(): HasMany
    {
        return $this->hasMany(ProjectChapterGuidance::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('chapter_number');
    }

    public function outlines(): HasMany
    {
        return $this->hasMany(ProjectOutline::class)->orderBy('display_order');
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(ProjectMetadata::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function documentCitations()
    {
        return $this->hasMany(DocumentCitation::class, 'document_id');
    }

    public function collectedPapers(): HasMany
    {
        return $this->hasMany(CollectedPaper::class);
    }

    /**
     * Get the university this project belongs to
     */
    public function universityRelation(): BelongsTo
    {
        return $this->belongsTo(University::class, 'university_id');
    }

    /**
     * Get the faculty this project belongs to
     */
    public function facultyRelation(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    /**
     * Get the department this project belongs to
     */
    public function departmentRelation(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function getAllCitations()
    {
        return $this->documentCitations()->with('citation');
    }

    public function getVerifiedCitationsCount()
    {
        return $this->documentCitations()
            ->whereHas('citation', function ($query) {
                $query->where('verification_status', 'verified');
            })
            ->count();
    }

    public function getUnverifiedCitationsCount()
    {
        return $this->documentCitations()
            ->where(function ($query) {
                $query->whereDoesntHave('citation')
                    ->orWhereHas('citation', function ($subQuery) {
                        $subQuery->where('verification_status', '!=', 'verified');
                    });
            })
            ->count();
    }

    public function getCurrentChapter()
    {
        return $this->chapters()->where('chapter_number', $this->current_chapter)->first();
    }

    public function getProgressPercentage(): float
    {
        $chapters = $this->relationLoaded('chapters')
            ? $this->chapters
            : $this->chapters()->get();

        if ($chapters->isEmpty()) {
            return 0.0;
        }

        // 1. Check if all chapters are marked as completed/approved
        $totalChapters = $chapters->count();
        $completedChapters = $chapters->whereIn('status', ['completed', 'approved'])->count();

        if ($completedChapters === $totalChapters) {
            return 100.0;
        }

        // 2. Calculate Target Word Count
        // Prefer sum of chapter targets if they exist and are significant
        $chapterTargetSum = (int) $chapters->sum('target_word_count');

        $outlines = $this->relationLoaded('outlines')
            ? $this->outlines
            : $this->outlines()->get();

        $outlineTargetSum = (int) $outlines
            ->where('is_required', true)
            ->sum('target_word_count');

        // Fallback to category default if outline target is 0
        if ($outlineTargetSum <= 0) {
            $category = $this->relationLoaded('category')
                ? $this->category
                : $this->category()->select('id', 'target_word_count')->first();
            $outlineTargetSum = (int) ($category?->target_word_count ?? 0);
        }

        // Use the larger of the two targets to avoid underestimation
        // But if chapter targets are 0 (not set), we rely on outline
        $targetWordCount = max($chapterTargetSum, $outlineTargetSum);

        // Final fallback
        if ($targetWordCount <= 0) {
            $targetWordCount = max($totalChapters, 1) * 2500;
        }

        // 3. Calculate Effective Words
        $effectiveWords = 0;
        $avgTargetPerChapter = $targetWordCount / max($totalChapters, 1);

        foreach ($chapters as $chapter) {
            if (in_array($chapter->status, ['completed', 'approved'])) {
                // If chapter has a specific target, use it.
                // If not, use the average share of the TOTAL target.
                $contribution = $chapter->target_word_count > 0
                    ? $chapter->target_word_count
                    : $avgTargetPerChapter;

                $effectiveWords += $contribution;
            } else {
                $effectiveWords += $chapter->word_count;
            }
        }

        $progress = ($effectiveWords / $targetWordCount) * 100;

        return round(min($progress, 100), 2);
    }

    /**
     * Calculate progress based on structured outlines
     */
    public function getStructuredProgressPercentage(): float
    {
        $totalOutlines = $this->outlines()->where('is_required', true)->count();

        if ($totalOutlines === 0) {
            return 0;
        }

        $completedOutlines = $this->outlines()
            ->where('is_required', true)
            ->get()
            ->filter(fn ($outline) => $outline->is_complete)
            ->count();

        return round(($completedOutlines / $totalOutlines) * 100, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get most recent active setup project
     */
    public function scopeActiveSetup($query)
    {
        return $query->where('status', 'setup')
            ->where('is_active', true)
            ->orderBy('last_activity_at', 'desc');
    }

    /**
     * Touch last activity timestamp
     */
    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Generate a unique slug for the project
     */
    public function generateSlug(): string
    {
        $baseSlug = Str::slug($this->title ?: 'project');
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate a unique slug from any text (for topic titles)
     */
    public function generateSlugFromText(string $text): string
    {
        $baseSlug = Str::slug($text);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if setup is complete
     */
    public function isSetupComplete(): bool
    {
        return $this->status !== 'setup' || $this->setup_step >= 4;
    }

    /**
     * Check if topic selection is complete
     */
    public function isTopicComplete(): bool
    {
        return $this->topic_status === 'topic_approved' && ! empty($this->topic);
    }

    /**
     * Get the next required step for this project
     */
    public function getNextRequiredStep(): string
    {
        // Convert enum values to strings for comparison
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;
        $topicStatus = $this->topic_status instanceof \BackedEnum ? $this->topic_status->value : $this->topic_status;

        // If setup is not complete, continue wizard
        if ($status === 'setup' && $this->setup_step < 4) {
            return 'wizard';
        }

        // If setup complete but no topic selected, go to topic selection
        if (($status === 'setup' || $status === 'topic_selection') &&
            ($topicStatus === 'not_started' || $topicStatus === 'topic_selection') &&
            empty($this->topic)) {
            return 'topic-selection';
        }

        // If topic is pending approval
        if ($topicStatus === 'topic_pending_approval') {
            return 'topic-approval';
        }

        // If topic approved and project is in guidance phase, go to guidance page
        if ($topicStatus === 'topic_approved' && $status === 'guidance') {
            return 'guidance';
        }

        // If topic approved and project is in writing phase, go to writing dashboard
        if ($topicStatus === 'topic_approved' &&
            in_array($status, ['writing', 'completed'])) {
            return 'writing';
        }

        // If project is completed, can view either project dashboard or writing
        if ($status === 'completed') {
            return 'writing'; // or 'project' depending on preference
        }

        // Default to main project view for other statuses
        return 'project';
    }

    /**
     * Enhanced Save Setup Progress with Activity Tracking
     */
    public function saveSetupProgress(int $step, array $data): void
    {
        // Deep merge existing data with new data
        $currentData = $this->setup_data ?? [];

        // Preserve all existing fields and only update provided ones
        $mergedData = array_merge($currentData, array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        }));

        $this->update([
            'setup_step' => max($this->setup_step, $step),
            'setup_data' => $mergedData,
            'last_activity_at' => now(),
        ]);

        Log::info('Project setup progress saved', [
            'project_id' => $this->id,
            'step' => $step,
            'merged_data' => $mergedData,
        ]);
    }

    /**
     * Complete the wizard setup with validation
     */
    public function completeSetup(array $finalData): void
    {
        Log::info('PROJECT SETUP COMPLETION - Starting completeSetup', [
            'project_id' => $this->id,
            'before_status' => $this->status,
            'before_slug' => $this->slug,
            'before_title' => $this->title,
            'final_data' => $finalData,
        ]);

        // Get all setup data from step-based structure
        $setupData = $this->getCleanSetupData();

        Log::info('PROJECT SETUP COMPLETION - Retrieved Setup Data', [
            'project_id' => $this->id,
            'setup_data' => $setupData,
        ]);

        // Map the incoming store format to our internal format
        $mappedData = [
            'projectType' => $finalData['type'] ?? $setupData['projectType'],
            'projectCategoryId' => $finalData['project_category_id'] ?? $setupData['projectCategoryId'],
            'universityId' => $finalData['university_id'] ?? $setupData['universityId'],
            'facultyId' => $finalData['faculty_id'] ?? $setupData['facultyId'],
            'departmentId' => $finalData['department_id'] ?? $setupData['departmentId'],
            'course' => $finalData['course'] ?? $setupData['course'],
            'fieldOfStudy' => $finalData['field_of_study'] ?? $setupData['fieldOfStudy'],
            'academicSession' => $finalData['academic_session'] ?? $setupData['academicSession'],
            'workingMode' => $finalData['mode'] ?? $setupData['workingMode'],
            'supervisorName' => $finalData['supervisor_name'] ?? $setupData['supervisorName'],
            'matricNumber' => $finalData['matric_number'] ?? $setupData['matricNumber'],
            'aiAssistanceLevel' => $finalData['ai_assistance_level'] ?? $setupData['aiAssistanceLevel'],
        ];

        $allData = array_merge($setupData, $mappedData);

        // Validate that all required data is present
        $requiredFields = [
            'projectType', 'projectCategoryId', 'universityId',
            'facultyId', 'departmentId', 'course',
            'academicSession', 'workingMode',
        ];

        foreach ($requiredFields as $field) {
            if (empty($allData[$field]) && $field !== 'projectCategoryId') {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        $this->update([
            'status' => 'setup',
            'topic_status' => 'topic_selection',
            'setup_step' => 4,
            'type' => $allData['projectType'],
            'project_category_id' => $allData['projectCategoryId'],
            'university_id' => $allData['universityId'],
            'faculty_id' => $allData['facultyId'],
            'department_id' => $allData['departmentId'],
            'course' => $allData['course'],
            'field_of_study' => $allData['fieldOfStudy'],
            'supervisor_name' => $allData['supervisorName'] ?? null,
            'mode' => $allData['workingMode'],
            'settings' => array_merge($this->settings ?? [], [
                'matric_number' => $allData['matricNumber'] ?? null,
                'academic_session' => $allData['academicSession'],
                'ai_assistance_level' => $allData['aiAssistanceLevel'] ?? 'moderate',
            ]),
            'setup_data' => null, // Clear setup data after completion
            'last_activity_at' => now(),
        ]);

        Log::info('PROJECT SETUP COMPLETION - Project Updated Successfully', [
            'project_id' => $this->id,
            'after_status' => $this->fresh()->status,
            'after_slug' => $this->fresh()->slug,
            'after_title' => $this->fresh()->title,
            'final_type' => $allData['projectType'],
            'university_id' => $allData['universityId'],
            'faculty_id' => $allData['facultyId'],
            'department_id' => $allData['departmentId'],
            'setup_data_cleared' => $this->fresh()->setup_data === null,
        ]);
    }

    /**
     * Check if setup data is complete for a given step
     */
    public function isStepDataComplete(int $step): bool
    {
        if (! $this->setup_data) {
            return false;
        }

        $requiredFields = [
            1 => ['projectType', 'projectCategoryId'],
            2 => ['universityId', 'facultyId', 'departmentId', 'course'],
            3 => ['fieldOfStudy', 'academicSession', 'workingMode'],
        ];

        if (! isset($requiredFields[$step])) {
            return true;
        }

        foreach ($requiredFields[$step] as $field) {
            // Special handling for university field when it's "other"
            if ($field === 'university' && isset($this->setup_data['university']) &&
                $this->setup_data['university'] === 'other') {
                if (empty($this->setup_data['otherUniversity'])) {
                    return false;
                }

                continue;
            }

            if (empty($this->setup_data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the actual current step based on completed data
     */
    public function getActualCurrentStep(): int
    {
        // Check each step in order to find the first incomplete one
        for ($step = 1; $step <= 3; $step++) {
            if (! $this->isStepDataComplete($step)) {
                return $step;
            }
        }

        // If all steps are complete, return step 3 (final step)
        return 3;
    }

    /**
     * Get cleaned setup data for frontend
     */
    public function getCleanSetupData(): array
    {
        // Get flat data from step-based structure
        $data = $this->getFlatSetupData();

        // Ensure all expected fields exist with defaults
        $defaults = [
            'projectType' => '',
            'projectCategoryId' => null,
            'university' => '',
            'otherUniversity' => '',
            'faculty' => '',
            'department' => '',
            'course' => '',
            'fieldOfStudy' => '',
            'supervisorName' => '',
            'matricNumber' => '',
            'academicSession' => '',
            'workingMode' => '',
            'aiAssistanceLevel' => 'moderate',
        ];

        return array_merge($defaults, $data);
    }

    /**
     * STAGE NAVIGATION METHODS
     * Allow users to go back to previous major stages and modify setup
     */
    public function goBackToWizard(): void
    {
        // Reset to setup mode while preserving completed data as setup_data
        $this->update([
            'status' => 'setup',
            'setup_step' => 3, // Go to last step of wizard
            'setup_data' => [
                'projectType' => $this->type,
                'projectCategoryId' => $this->project_category_id,
                'university' => $this->university,
                'faculty' => $this->faculty,
                'course' => $this->course,
                'fieldOfStudy' => $this->field_of_study,
                'supervisorName' => $this->supervisor_name,
                'workingMode' => $this->mode,
                'department' => $this->settings['department'] ?? null,
                'matricNumber' => $this->settings['matric_number'] ?? null,
                'academicSession' => $this->settings['academic_session'] ?? null,
                'aiAssistanceLevel' => $this->settings['ai_assistance_level'] ?? 'moderate',
            ],
        ]);
    }

    public function goBackToTopicSelection(): void
    {
        // Reset topic but keep project setup data
        $this->update([
            'status' => 'setup',
            'topic_status' => 'topic_selection',
            'topic' => null,
            'title' => null,
        ]);
    }

    public function goBackToTopicApproval(): void
    {
        // Reset to topic approval stage from writing
        $this->update([
            'status' => 'topic_pending_approval',
        ]);
    }

    /**
     * STEP-BASED DATA STRUCTURE METHODS
     * These methods handle the new step-based setup_data format
     */

    /**
     * Get step-based setup data
     */
    public function getStepBasedSetupData(): array
    {
        return $this->setup_data ?? [
            'format_version' => '2.0',
            'steps' => [],
            'current_step' => 1,
            'furthest_completed_step' => 0,
        ];
    }

    /**
     * Save data for a specific step
     */
    public function saveStepData(int $step, array $data): void
    {
        $stepBasedData = $this->getStepBasedSetupData();

        // Update step data
        $stepBasedData['steps'][(string) $step] = [
            'data' => $data,
            'completed' => $this->isStepComplete($step, $data),
            'timestamp' => now()->toISOString(),
        ];

        // Update current step and furthest completed
        $stepBasedData['current_step'] = max($stepBasedData['current_step'] ?? 1, $step);
        if ($stepBasedData['steps'][(string) $step]['completed']) {
            $stepBasedData['furthest_completed_step'] = max($stepBasedData['furthest_completed_step'] ?? 0, $step);
        }

        $this->update([
            'setup_data' => $stepBasedData,
            'setup_step' => $stepBasedData['current_step'],
        ]);
    }

    /**
     * Check if step data is complete
     */
    private function isStepComplete(int $step, array $data): bool
    {
        $requiredFields = [
            1 => ['projectType', 'projectCategoryId'],
            2 => ['universityId', 'facultyId', 'departmentId', 'course'],
            3 => ['fieldOfStudy', 'academicSession', 'workingMode'],
        ];

        $required = $requiredFields[$step] ?? [];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get flat data from step-based structure (for frontend form compatibility)
     */
    public function getFlatSetupData(): array
    {
        $stepBasedData = $this->getStepBasedSetupData();
        $flatData = [];

        if (isset($stepBasedData['steps'])) {
            foreach ($stepBasedData['steps'] as $stepData) {
                $flatData = array_merge($flatData, $stepData['data'] ?? []);
            }
        }

        return $flatData;
    }

    /**
     * Get the full university name (without abbreviations)
     */
    public function getFullUniversityNameAttribute(): string
    {
        return $this->universityRelation?->name ?? 'TBD';
    }

    /**
     * Get the faculty name
     */
    public function getFacultyNameAttribute(): string
    {
        return $this->facultyRelation?->name ?? 'TBD';
    }

    /**
     * Get the department name
     */
    public function getDepartmentNameAttribute(): string
    {
        return $this->departmentRelation?->name ?? 'TBD';
    }

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->slug)) {
                $project->slug = $project->generateSlug();
            }
        });

        static::updating(function ($project) {
            if ($project->isDirty('title') && empty($project->slug)) {
                $project->slug = $project->generateSlug();
            }
        });

        /**
         * CASCADE DELETION
         * When a project is deleted, delete all related data
         * Note: Database foreign key constraints handle most cascade deletion,
         * but we explicitly clean up here for any edge cases
         */
        static::deleting(function ($project) {
            // Delete all chapters (also handled by DB constraint)
            $project->chapters()->delete();

            // Delete project metadata (also handled by DB constraint)
            $project->metadata()->delete();
        });
    }

    /**
     * Get the progress of the latest generation attempt
     */
    public function getLatestGenerationProgress(): ?int
    {
        return \App\Models\ProjectGeneration::where('project_id', $this->id)
            ->latest()
            ->value('progress');
    }
}
