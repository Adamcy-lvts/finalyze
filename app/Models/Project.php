<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectTopicStatus;
use App\Enums\ChapterStatus;
use App\Services\UniversityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'user_id', 'project_category_id', 'title', 'slug', 'topic', 'description', 'type', 'status', 'topic_status',
        'mode', 'field_of_study', 'university', 'course',
        'supervisor_name', 'current_chapter', 'is_active', 'settings',
        'setup_step', 'setup_data', 'last_activity_at',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'topic_status' => ProjectTopicStatus::class,
        'settings' => 'array',
        'setup_data' => 'array',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('chapter_number');
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(ProjectMetadata::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function getCurrentChapter()
    {
        return $this->chapters()->where('chapter_number', $this->current_chapter)->first();
    }

    public function getProgressPercentage()
    {
        $totalChapters = 5;
        $completedChapters = $this->chapters()->where('status', 'approved')->count();

        return ($completedChapters / $totalChapters) * 100;
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
        if (($status === 'setup' || $status === 'planning') && 
            ($topicStatus === 'not_started' || $topicStatus === 'topic_selection') && 
            empty($this->topic)) {
            return 'topic-selection';
        }

        // If topic is pending approval
        if ($topicStatus === 'topic_pending_approval') {
            return 'topic-approval';
        }

        // If topic approved and project is in writing phase, go to writing dashboard
        if ($topicStatus === 'topic_approved' && 
            in_array($status, ['planning', 'writing'])) {
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
            'university' => $finalData['university'] ?? $setupData['university'],
            'faculty' => $finalData['faculty'] ?? $setupData['faculty'],
            'department' => $finalData['department'] ?? $setupData['department'],
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
            'projectType', 'projectCategoryId', 'university',
            'faculty', 'department', 'course',
            'academicSession', 'workingMode',
        ];

        foreach ($requiredFields as $field) {
            if (empty($allData[$field]) && $field !== 'projectCategoryId') {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Handle special case for "other" university
        $universityValue = $allData['university'];
        if ($universityValue === 'other' && ! empty($allData['otherUniversity'])) {
            $universityValue = $allData['otherUniversity'];
        }

        $this->update([
            'status' => 'topic_selection',
            'setup_step' => 4,
            'type' => $allData['projectType'],
            'project_category_id' => $allData['projectCategoryId'],
            'university' => $universityValue,
            'course' => $allData['course'],
            'field_of_study' => $allData['fieldOfStudy'],
            'supervisor_name' => $allData['supervisorName'] ?? null,
            'mode' => $allData['workingMode'],
            'settings' => array_merge($this->settings ?? [], [
                'faculty' => $allData['faculty'],
                'department' => $allData['department'],
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
            'university' => $universityValue,
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
            2 => ['university', 'faculty', 'department', 'course'],
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
                'course' => $this->course,
                'fieldOfStudy' => $this->field_of_study,
                'supervisorName' => $this->supervisor_name,
                'workingMode' => $this->mode,
                'faculty' => $this->settings['faculty'] ?? null,
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
            'status' => 'topic_selection',
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
            2 => ['university', 'faculty', 'department', 'course'],
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
        return UniversityService::getFullName($this->university);
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
}
