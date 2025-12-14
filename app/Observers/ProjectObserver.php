<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Project;

class ProjectObserver
{
    public function created(Project $project): void
    {
        $userEmail = $project->user?->email ?? "user#{$project->user_id}";
        $title = $project->title ?: 'Untitled project';

        ActivityLog::record(
            'project.created',
            "Project created: {$title} ({$userEmail})",
            $project,
            $project->user
        );
    }
}

