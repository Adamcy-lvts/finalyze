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
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::info('🛡️ MIDDLEWARE - Processing request', [
                'url' => $request->url(),
                'route_name' => $request->route()?->getName(),
                'path' => $request->path(),
                'is_stream_pattern' => $request->is('projects/*/topics/stream'),
            ]);
        }

        // Skip citation verification routes entirely
        if ($request->is('api/chapters/*/verify-citations')) {
            return $next($request);
        }

        // Skip defense question generation routes entirely
        if ($request->is('api/chapters/*/defense-questions')) {
            return $next($request);
        }

        // Skip chapter analysis API routes entirely
        if ($request->is('api/chapters/*/analyze') ||
            $request->is('api/chapters/*/analysis/*') ||
            $request->is('api/chapters/*/analysis/latest') ||
            $request->is('api/chapters/*/analysis/history') ||
            $request->is('api/projects/analysis/overview') ||
            $request->is('api/projects/*/analysis/*')) {
            return $next($request);
        }

        // Skip data collection API routes entirely
        if ($request->is('api/chapters/*/detect') ||
            $request->is('api/chapters/*/placeholder') ||
            $request->is('api/chapters/*/suggestions') ||
            $request->is('api/chapters/*/insert-template') ||
            $request->is('api/data-collection/template*')) {
            return $next($request);
        }

        // Skip university, faculty, and department API routes entirely
        if ($request->is('api/universities') ||
            $request->is('api/faculties') ||
            $request->is('api/faculties/*/departments') ||
            $request->is('api/departments')) {
            return $next($request);
        }

        // Skip manual editor routes entirely
        if ($request->is('projects/*/manual-editor/*') ||
            $request->is('projects/*/manual-editor/*/mark-complete') ||
            str_starts_with($request->route()?->getName() ?? '', 'projects.manual-editor.')) {
            return $next($request);
        }

        // Skip topic streaming routes entirely
        if ($request->is('projects/*/topics/stream') || $request->route()?->getName() === 'topics.stream') {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::info('🛡️ MIDDLEWARE - Skipping for topics.stream route', [
                    'url' => $request->url(),
                    'route_name' => $request->route()?->getName(),
                    'is_stream_route' => $request->is('projects/*/topics/stream'),
                ]);
            }

            return $next($request);
        }

        // Skip topic lab and chat routes entirely
        if ($request->route()?->getName() === 'topics.lab' || 
            $request->route()?->getName() === 'topics.chat' ||
            $request->route()?->getName() === 'topics.chat.rename' ||
            $request->route()?->getName() === 'topics.chat.save-topic' ||
            $request->route()?->getName() === 'topics.chat.delete-session') {
            return $next($request);
        }

        // Skip guidance streaming routes entirely
        if ($request->is('api/projects/*/guidance/stream-bulk-generation')) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::info('🛡️ MIDDLEWARE - Skipping for guidance.stream route', [
                    'url' => $request->url(),
                    'route_name' => $request->route()?->getName(),
                    'is_guidance_stream_route' => $request->is('api/projects/*/guidance/stream-bulk-generation'),
                ]);
            }

            return $next($request);
        }

        // Skip bulk generation routes entirely (including API endpoints)
        if ($request->is('projects/*/bulk-generate') ||
            $request->is('api/projects/*/bulk-generate/*') ||
            $request->route()?->getName() === 'projects.bulk-generate' ||
            str_starts_with($request->route()?->getName() ?? '', 'api.projects.bulk-generate.')) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::info('🛡️ MIDDLEWARE - Skipping for bulk generation routes', [
                    'url' => $request->url(),
                    'route_name' => $request->route()?->getName(),
                    'is_bulk_generate_route' => $request->is('projects/*/bulk-generate'),
                    'is_bulk_api_route' => $request->is('api/projects/*/bulk-generate/*'),
                ]);
            }

            return $next($request);
        }

        // Skip middleware for project mode updates
        if ($request->is('projects/*/update-mode')) {
            return $next($request);
        }

        // Skip manual complete action
        if ($request->is('projects/*/complete') || $request->route()?->getName() === 'projects.complete') {
            return $next($request);
        }

        // Skip middleware for AJAX requests, but NOT for Inertia requests
        if (($request->ajax() || $request->wantsJson()) && ! $request->header('X-Inertia')) {
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

        $currentRoute = $request->route()?->getName();
        $requiredStep = $project->getNextRequiredStep();
        $projectSlug = $project->slug;

        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::info('🔍 MIDDLEWARE - Project state analysis', [
                'project_id' => $project->id,
                'project_slug' => $projectSlug,
                'current_route' => $currentRoute,
                'project_status' => $project->status,
                'topic_status' => $project->topic_status,
                'has_topic' => ! empty($project->topic),
                'required_step' => $requiredStep,
                'url' => $request->url(),
                'is_guidance_route' => $currentRoute === 'projects.guidance',
            ]);
        }

        // Allow access to chapter editing routes when project is in writing mode
        $projectStatus = $project->status instanceof \BackedEnum ? $project->status->value : $project->status;
        $topicStatus = $project->topic_status instanceof \BackedEnum ? $project->topic_status->value : $project->topic_status;

        $allowedRoutes = [
            'chapters.write', 'chapters.edit', 'chapters.stream', 'chapters.save', 'chapters.generate',
            'chapters.chat', 'chapters.chat-history', 'chapters.chat-stream',
            'chapters.chat-upload', 'chapters.chat-files', 'chapters.chat-file-delete',
            'chapters.chat-search', 'chapters.chat-sessions', 'chapters.chat-session-delete',
            'chapters.chat-message-delete', 'chapters.chat-clear',
            'projects.guidance', 'projects.guidance-chapter', 'projects.writing-guidelines',
            'projects.bulk-generate', 'api.projects.bulk-generate.stream',
            'api.projects.bulk-generate.start', 'api.projects.bulk-generate.status', 'api.projects.bulk-generate.cancel',
            'projects.analysis',

        ];

        $isAllowedRoute = in_array($currentRoute, $allowedRoutes);
        $hasWritingStatus = in_array($projectStatus, ['guidance', 'writing', 'completed']);
        $hasApprovedTopic = $topicStatus === 'topic_approved';

        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::info('🔐 MIDDLEWARE - Route check', [
                'current_route' => $currentRoute,
                'is_allowed_route' => $isAllowedRoute,
                'project_status' => $projectStatus,
                'has_writing_status' => $hasWritingStatus,
                'topic_status' => $topicStatus,
                'has_approved_topic' => $hasApprovedTopic,
                'will_allow' => $isAllowedRoute && $hasWritingStatus && $hasApprovedTopic,
            ]);
        }

        if ($isAllowedRoute && $hasWritingStatus && $hasApprovedTopic) {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::info('✅ MIDDLEWARE - Allowing guidance route');
            }

            return $next($request);
        }

        // Allow PDF export and topic approval when in topic approval stage
        if (in_array($currentRoute, ['topics.export-pdf', 'topics.approve']) && $topicStatus === 'topic_pending_approval') {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::info('🔓 MIDDLEWARE - Allowing topic approval action', [
                    'project_id' => $project->id,
                    'current_route' => $currentRoute,
                    'topic_status' => $topicStatus,
                ]);
            }

            return $next($request);
        }

        // Define the correct route for each step
        $stepRoutes = [
            'wizard' => 'projects.create',
            'topic-selection' => 'projects.topic-selection',
            'topic-approval' => 'projects.topic-approval',
            'guidance' => 'projects.guidance',
            'writing' => 'projects.writing',
            'project' => 'projects.show',
        ];

        $targetRoute = $stepRoutes[$requiredStep] ?? 'projects.show';

        // Allow backward navigation - users can always go to previous stages
        $allowedBackwardRoutes = [
            'projects.topic-selection' => ['projects.create'], // From topic selection back to wizard
            'projects.topic-approval' => ['projects.topic-selection'], // From approval back to topic selection
            'projects.guidance' => ['projects.topic-approval'], // From guidance back to approval
            'projects.writing' => ['projects.guidance'], // From writing back to guidance
        ];

        if (isset($allowedBackwardRoutes[$targetRoute]) &&
            in_array($currentRoute, $allowedBackwardRoutes[$targetRoute])) {
            return $next($request);
        }

        // If user is not on the correct route for their project state, redirect
        if ($currentRoute !== $targetRoute && $requiredStep !== 'project') {
            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::info('🔄 MIDDLEWARE - Redirecting user', [
                    'project_id' => $project->id,
                    'from_route' => $currentRoute,
                    'to_route' => $targetRoute,
                    'required_step' => $requiredStep,
                    'reason' => 'User not on correct route for project state',
                ]);
            }

            if ($requiredStep === 'wizard') {
                return redirect()->route('projects.create')->with('resume_project', $project->id);
            } else {
                return redirect()->route($targetRoute, $projectSlug);
            }
        }

        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::info('✅ MIDDLEWARE - Allowing request', [
                'project_id' => $project->id,
                'route' => $currentRoute,
                'required_step' => $requiredStep,
                'reason' => 'User is on correct route or step is project',
            ]);
        }

        return $next($request);
    }
}
