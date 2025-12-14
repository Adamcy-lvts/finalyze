<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogAiActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('activity.ai_endpoints', true)) {
            /** @var Response $response */
            return $next($request);
        }

        $route = $request->route();
        $routeName = $route?->getName();

        $shouldLog = $this->shouldLog($request, $routeName);
        $start = hrtime(true);

        /** @var Response $response */
        $response = $next($request);

        if (! $shouldLog) {
            return $response;
        }

        $durationMs = (int) round((hrtime(true) - $start) / 1_000_000);
        $user = $request->user();

        $params = $route?->parameters() ?? [];
        $projectId = $params['project'] ?? $params['project_id'] ?? null;
        $chapter = $params['chapter'] ?? $params['chapter_id'] ?? $params['chapterNumber'] ?? null;

        $input = $request->all();
        $inputKeys = array_values(array_slice(array_keys($input), 0, 40));

        // Never store the actual prompt/content; store safe sizes/identifiers only.
        $safeInputMeta = [
            'keys' => $inputKeys,
            'sizes' => array_filter([
                'message_len' => isset($input['message']) ? strlen((string) $input['message']) : null,
                'prompt_len' => isset($input['prompt']) ? strlen((string) $input['prompt']) : null,
                'text_len' => isset($input['text']) ? strlen((string) $input['text']) : null,
                'content_len' => isset($input['content']) ? strlen((string) $input['content']) : null,
                'selected_text_len' => isset($input['selectedText']) ? strlen((string) $input['selectedText']) : null,
                'chapter_content_len' => isset($input['chapterContent']) ? strlen((string) $input['chapterContent']) : null,
            ], fn ($v) => $v !== null),
        ];

        $responseMeta = [
            'status' => $response->getStatusCode(),
            'streaming' => $response instanceof StreamedResponse,
        ];

        if (! $responseMeta['streaming'] && method_exists($response, 'getContent')) {
            $content = (string) $response->getContent();
            $responseMeta['response_size'] = strlen($content);

            if (Str::contains((string) $response->headers->get('content-type'), 'application/json')) {
                $json = json_decode($content, true);
                if (is_array($json)) {
                    $responseMeta['json_keys'] = array_values(array_slice(array_keys($json), 0, 40));
                    $usage = Arr::get($json, 'usage');
                    if (is_array($usage)) {
                        $responseMeta['usage'] = Arr::only($usage, ['prompt_tokens', 'completion_tokens', 'total_tokens']);
                    }
                }
            }
        }

        $type = $this->typeFor($routeName, $request->path());
        $message = $routeName
            ? "AI endpoint: {$routeName}"
            : "AI endpoint: {$request->method()} {$request->path()}";

        ActivityLog::record(
            $type,
            $message,
            null,
            $user,
            array_filter([
                'route' => $routeName,
                'method' => $request->method(),
                'path' => $request->path(),
                'duration_ms' => $durationMs,
                'project' => $projectId ? (string) $projectId : null,
                'chapter' => $chapter ? (string) $chapter : null,
                'input' => $safeInputMeta,
                'response' => $responseMeta,
            ], fn ($v) => $v !== null)
        );

        return $response;
    }

    private function shouldLog(Request $request, ?string $routeName): bool
    {
        if (! $routeName) {
            return false;
        }

        // API: log AI-related routes by name conventions
        if (Str::startsWith($routeName, 'api.')) {
            return Str::startsWith($routeName, 'api.ai.')
                || Str::contains($routeName, '.analysis.')
                || Str::contains($routeName, '.defense.')
                || Str::contains($routeName, 'citations.')
                || Str::contains($routeName, 'paper-collection.')
                || Str::contains($routeName, 'data-collection.')
                || Str::contains($routeName, 'bulk-generate.')
                || Str::contains($routeName, 'stream-bulk-generation')
                || Str::contains($routeName, 'regenerate');
        }

        // Web: chapter/AI endpoints (generation, streaming, quick actions, manual editor AI)
        if (Str::startsWith($routeName, 'chapters.')) {
            return Str::contains($routeName, 'chat')
                || Str::contains($routeName, 'stream')
                || Str::contains($routeName, 'quick-actions')
                || Str::contains($routeName, 'generate')
                || Str::contains($routeName, 'ai-generate');
        }

        if (Str::startsWith($routeName, 'projects.manual-editor.')) {
            // Exclude pure CRUD-style endpoints
            return ! in_array($routeName, [
                'projects.manual-editor.show',
                'projects.manual-editor.save',
                'projects.manual-editor.mark-complete',
            ], true);
        }

        // Topic generation / lab endpoints
        if (Str::startsWith($routeName, 'topics.')) {
            return true;
        }

        return false;
    }

    private function typeFor(?string $routeName, string $path): string
    {
        if ($routeName) {
            return 'ai.endpoint';
        }

        return Str::startsWith($path, 'api/') ? 'ai.api' : 'ai.web';
    }
}
