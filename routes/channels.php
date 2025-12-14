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
 * For general user notifications and real-time balance updates
 */
Broadcast::channel('user.{id}', function ($user, $id) {
    $authorized = (int) $user->id === (int) $id;

    \Illuminate\Support\Facades\Log::info('Channel authorization attempt', [
        'channel' => "user.{$id}",
        'user_id' => $user->id,
        'requested_id' => $id,
        'authorized' => $authorized,
    ]);

    return $authorized;
});

/**
 * Admin AI channel
 * Only admins/support can listen for provisioning updates.
 */
Broadcast::channel('admin.ai', function ($user) {
    return $user && $user->hasAnyRole(['super_admin', 'admin', 'support']);
});

/**
 * Admin Notifications channel
 * Only admins/support can listen for admin notification updates.
 */
Broadcast::channel('admin.notifications', function ($user) {
    return $user && $user->hasAnyRole(['super_admin', 'admin', 'support']);
});
