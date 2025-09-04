<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with user's active project and recent activity.
     * Provides comprehensive statistics and project overview for the authenticated user.
     */
    public function index()
    {
        $user = auth()->user();
        $activeProject = $user->projects()
            ->where('is_active', true)
            ->with('chapters')
            ->first();

        $stats = [
            'totalProjects' => $user->projects()->count(),
            'completedChapters' => $activeProject
                ? $activeProject->chapters()->where('status', 'approved')->count()
                : 0,
            'totalChapters' => $activeProject
                ? $activeProject->chapters()->count()
                : 0,
            'totalWords' => $activeProject
                ? $activeProject->chapters()->sum('word_count')
                : 0,
        ];

        $recentActivities = collect(); // Will implement activity tracking later

        if ($activeProject) {
            $activeProject = [
                'id' => $activeProject->id,
                'slug' => $activeProject->slug,
                'title' => $activeProject->title,
                'type' => $activeProject->type,
                'progress' => $activeProject->getProgressPercentage(),
                'currentChapter' => $activeProject->current_chapter,
                'chapters' => $activeProject->chapters->map(function ($chapter) {
                    return [
                        'number' => $chapter->chapter_number,
                        'status' => $chapter->status,
                    ];
                }),
            ];
        }

        return Inertia::render('Dashboard', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'stats' => $stats,
            'activeProject' => $activeProject,
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Generate recent activities based on project data.
     * This is a simplified version - can be enhanced with proper activity logging.
     */
    private function generateRecentActivities($projects)
    {
        $activities = collect();

        // Add project creation activities
        foreach ($projects->take(3) as $project) {
            $activities->push([
                'id' => 'project_'.$project->id,
                'type' => 'project_created',
                'description' => 'Created project: '.($project->title ?: 'Untitled Project'),
                'time' => $project->created_at->diffForHumans(),
            ]);
        }

        // Add chapter completion activities
        $completedChapters = $projects->flatMap->chapters
            ->where('status', 'approved')
            ->take(2);

        foreach ($completedChapters as $chapter) {
            $activities->push([
                'id' => 'chapter_'.$chapter->id,
                'type' => 'chapter_completed',
                'description' => 'Completed '.$chapter->title,
                'time' => $chapter->updated_at->diffForHumans(),
            ]);
        }

        // Sort by most recent and limit to 5 activities
        return $activities
            ->sortByDesc(function ($activity) {
                return $activity['time'];
            })
            ->values()
            ->take(5)
            ->toArray();
    }
}
