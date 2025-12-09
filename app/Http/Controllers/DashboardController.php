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

        // Get active project with chapters
        $activeProject = $user->projects()
            ->where('is_active', true)
            ->with(['chapters' => function ($query) {
                $query->orderBy('chapter_number');
            }])
            ->latest('last_activity_at')
            ->first();

        // Calculate global stats
        $projects = $user->projects()->withCount(['chapters', 'documentCitations'])->get();

        $totalProjects = $projects->count();
        $activeProjectsCount = $projects->where('is_active', true)->count();
        $completedProjects = $projects->where('status', 'completed')->count();

        // Calculate total words across all projects
        // We need to load chapters for all projects to sum words, or use a join.
        // For performance on small scale, loading is fine. For larger, use aggregate query.
        $totalWords = 0;
        $totalChapters = 0;

        foreach ($projects as $project) {
            $totalWords += $project->chapters()->sum('word_count');
            $totalChapters += $project->chapters_count;
        }

        $avgWordsPerChapter = $totalChapters > 0 ? round($totalWords / $totalChapters) : 0;

        // Count citations
        $citationsAdded = $projects->sum('document_citations_count');

        // Count collected papers
        $researchPapers = \App\Models\CollectedPaper::where('user_id', $user->id)->count();

        // Count defense questions
        $defenseQuestions = \App\Models\DefenseQuestion::where('user_id', $user->id)->count();

        // Count AI interactions
        $aiAssistanceUsed = \App\Models\ChatConversation::where('user_id', $user->id)
            ->where('message_type', 'ai')
            ->count();

        // Estimate hours spent (rough estimate: 500 words per hour + 1 hour per chapter for research/editing)
        $hoursSpent = round(($totalWords / 500) + ($totalChapters * 1));

        $stats = [
            'totalProjects' => $totalProjects,
            'activeProjects' => $activeProjectsCount,
            'completedProjects' => $completedProjects,
            'totalWords' => $totalWords,
            'avgWordsPerChapter' => $avgWordsPerChapter,
            'citationsAdded' => $citationsAdded,
            'researchPapers' => $researchPapers,
            'defenseQuestions' => $defenseQuestions,
            'aiAssistanceUsed' => $aiAssistanceUsed,
            'hoursSpent' => $hoursSpent,
            'weeklyGoalProgress' => 0, // Placeholder
        ];

        $recentActivities = $this->generateRecentActivities($projects);

        if ($activeProject) {
            // Get the status value (handle enum)
            $statusValue = $activeProject->status instanceof \App\Enums\ProjectStatus
                ? $activeProject->status->value
                : $activeProject->status;

            $activeProjectData = [
                'id' => $activeProject->id,
                'slug' => $activeProject->slug,
                'title' => $activeProject->title,
                'type' => $activeProject->type,
                'status' => $statusValue,
                'setupStep' => $activeProject->setup_step,
                'progress' => $activeProject->getProgressPercentage(),
                'currentChapter' => $activeProject->current_chapter,
                'chapters' => $activeProject->chapters->map(function ($chapter) {
                    return [
                        'number' => $chapter->chapter_number,
                        'status' => $chapter->status,
                        'word_count' => $chapter->word_count,
                        'target_word_count' => $chapter->target_word_count,
                    ];
                }),
            ];
        } else {
            $activeProjectData = null;
        }

        return Inertia::render('Dashboard', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'stats' => $stats,
            'activeProject' => $activeProjectData,
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
