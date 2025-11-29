<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Project Generation Channel
 *
 * Authorizes users to listen to generation progress for their own projects.
 * Channel format: project.{projectId}.generation
 */
Broadcast::channel('project.{projectId}.generation', function ($user, $projectId) {
    $project = Project::find($projectId);

    if (! $project) {
        return false;
    }

    // Only the project owner can listen to generation events
    return (int) $user->id === (int) $project->user_id;
});

/**
 * User Notifications Channel
 *
 * For general user notifications (optional - for future use)
 */
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
