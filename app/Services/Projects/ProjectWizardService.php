<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ProjectWizardService
{
    /**
     * Save wizard progress, handling reuse or creation of setup projects.
     */
    public function saveProgress(User $user, array $payload): Project
    {
        $step = (int) $payload['step'];
        $data = $this->filterData($payload['data'] ?? []);

        if (! empty($payload['project_id'])) {
            $project = $this->getExistingSetupProject($user, (int) $payload['project_id']);
            $project->saveStepData($step, $data);

            if (! app()->isProduction()) {
                Log::info('Wizard progress updated (existing project)', [
                    'project_id' => $project->id,
                    'step' => $step,
                    'step_data_keys' => array_keys($data),
                ]);
            }

            return $project;
        }

        // Ensure at most one active setup project
        $this->cleanupSetupProjects($user);

        $project = $this->reuseOrCreateSetupProject($user, $step, $data);
        $project->saveStepData($step, $data);

        if (! app()->isProduction()) {
            $fresh = $project->fresh(['status', 'is_active']);
            Log::info('Wizard progress saved (reuse/create)', [
                'project_id' => $project->id,
                'step' => $step,
                'final_status' => $fresh->status,
                'final_is_active' => $fresh->is_active,
                'action_taken' => $project->wasRecentlyCreated ? 'created_new' : 'reused_existing',
            ]);
        }

        return $project;
    }

    private function filterData(array $data): array
    {
        return array_filter($data, fn($value) => $value !== null && $value !== '');
    }

    private function getExistingSetupProject(User $user, int $projectId): Project
    {
        return $user->projects()
            ->where('id', $projectId)
            ->where('status', 'setup')
            ->firstOrFail();
    }

    private function reuseOrCreateSetupProject(User $user, int $step, array $data): Project
    {
        $projectType = $data['projectType'] ?? 'undergraduate';
        $projectCategoryId = $data['projectCategoryId'] ?? null;

        $existingSetupProject = $user->projects()
            ->where('status', 'setup')
            ->latest('updated_at')
            ->first();

        if ($existingSetupProject) {
            $user->projects()
                ->where('status', 'setup')
                ->where('id', '!=', $existingSetupProject->id)
                ->update(['is_active' => false]);

            $existingSetupProject->update(['is_active' => true]);

            if (! app()->isProduction()) {
                Log::info('Wizard reuse existing setup project', [
                    'user_id' => $user->id,
                    'step' => $step,
                    'reused_project_id' => $existingSetupProject->id,
                ]);
            }

            return tap($existingSetupProject)->setRelation('user', $user);
        }

        $user->projects()
            ->where('status', 'setup')
            ->update(['is_active' => false]);

        $project = $user->projects()->create([
            'status' => 'setup',
            'setup_step' => 1,
            'setup_data' => [
                'format_version' => '2.0',
                'steps' => [],
                'current_step' => 1,
                'furthest_completed_step' => 0,
            ],
            'current_chapter' => 0,
            'is_active' => true,
            'type' => $projectType,
            'project_category_id' => $projectCategoryId,
            'field_of_study' => null,
            'university' => 'TBD',
            'course' => 'TBD',
            'title' => 'Project Setup in Progress',
        ]);

        if (! app()->isProduction()) {
            Log::info('Wizard created new setup project', [
                'user_id' => $user->id,
                'step' => $step,
                'project_id' => $project->id,
                'project_type' => $projectType,
                'project_category_id' => $projectCategoryId,
                'step_data_keys' => array_keys($data),
            ]);
        }

        return $project;
    }

    private function cleanupSetupProjects(User $user): void
    {
        $setupProjects = $user->projects()
            ->where('status', 'setup')
            ->orderBy('updated_at', 'desc')
            ->get();

        if ($setupProjects->count() <= 1) {
            return;
        }

        $keepProject = $setupProjects->first();
        $duplicates = $setupProjects->skip(1);

        $duplicates->each(fn(Project $proj) => $proj->update(['is_active' => false]));
        $keepProject->update(['is_active' => true]);

        if (! app()->isProduction()) {
            Log::info('Wizard cleanup duplicate setup projects', [
                'user_id' => $user->id,
                'kept_project_id' => $keepProject->id,
                'deactivated_ids' => $duplicates->pluck('id')->all(),
            ]);
        }
    }
}
