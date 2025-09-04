<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProjectStateMiddleware
{
    /**
     * Handle an incoming request.
     * Redirects projects to the correct state-based page
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip middleware for AJAX requests, but NOT for Inertia requests
        if (($request->ajax() || $request->wantsJson()) && !$request->header('X-Inertia')) {
            return $next($request);
        }

        // Only apply to project routes
        if (! $request->route('project')) {
            return $next($request);
        }

        $project = $request->route('project');

        // Ensure it's a Project model instance
        if (! $project instanceof Project) {
            return $next($request);
        }

        // Ensure user owns the project
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }

        // Skip state enforcement if user explicitly navigated back
        // This allows backward navigation between stages
        if ($request->session()->has('explicit_navigation')) {
            $request->session()->forget('explicit_navigation');

            return $next($request);
        }

        $currentRoute = $request->route()->getName();
        $requiredStep = $project->getNextRequiredStep();
        $projectSlug = $project->slug;

        // Allow access to chapter editing routes when project is in writing mode
        $projectStatus = $project->status instanceof \BackedEnum ? $project->status->value : $project->status;
        $topicStatus = $project->topic_status instanceof \BackedEnum ? $project->topic_status->value : $project->topic_status;
        
        if (in_array($currentRoute, ['chapters.edit', 'chapters.stream', 'chapters.save', 'chapters.generate', 'chapters.chat', 'chapters.chat-history', 'chapters.chat-stream']) &&
            in_array($projectStatus, ['writing', 'completed']) && $topicStatus === 'topic_approved') {
            return $next($request);
        }

        // Allow PDF export when in topic approval stage
        if ($currentRoute === 'topics.export-pdf' && $topicStatus === 'topic_pending_approval') {
            return $next($request);
        }

        // Define the correct route for each step
        $stepRoutes = [
            'wizard' => 'projects.create',
            'topic-selection' => 'projects.topic-selection',
            'topic-approval' => 'projects.topic-approval',
            'writing' => 'projects.writing',
            'project' => 'projects.show',
        ];

        $targetRoute = $stepRoutes[$requiredStep] ?? 'projects.show';

        // Allow backward navigation - users can always go to previous stages
        $allowedBackwardRoutes = [
            'projects.topic-selection' => ['projects.create'], // From topic selection back to wizard
            'projects.topic-approval' => ['projects.topic-selection'], // From approval back to topic selection
            'projects.writing' => ['projects.topic-approval'], // From writing back to approval
        ];

        if (isset($allowedBackwardRoutes[$targetRoute]) &&
            in_array($currentRoute, $allowedBackwardRoutes[$targetRoute])) {
            return $next($request);
        }

        // If user is not on the correct route for their project state, redirect
        if ($currentRoute !== $targetRoute && $requiredStep !== 'project') {
            if ($requiredStep === 'wizard') {
                return redirect()->route('projects.create')->with('resume_project', $project->id);
            } else {
                return redirect()->route($targetRoute, $projectSlug);
            }
        }

        return $next($request);
    }
}
