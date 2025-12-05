<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents duplicate requests from being processed simultaneously
 *
 * Uses cache locks to ensure only one request with the same fingerprint
 * can be processed at a time. Useful for preventing double-clicks on
 * expensive operations like AI generation.
 */
class PreventDuplicateRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $lockDuration = 10): Response
    {
        // Generate a unique fingerprint for this request
        $fingerprint = $this->generateFingerprint($request);

        // Try to acquire a lock
        $lock = Cache::lock($fingerprint, $lockDuration);

        if (! $lock->get()) {
            // Another request with the same fingerprint is already being processed
            return response()->json([
                'success' => false,
                'message' => 'A similar request is already being processed. Please wait.',
                'error' => 'duplicate_request',
            ], 409); // 409 Conflict
        }

        try {
            // Process the request
            $response = $next($request);

            // Release the lock after successful processing
            $lock->release();

            return $response;
        } catch (\Exception $e) {
            // Release the lock on error
            $lock->release();

            throw $e;
        }
    }

    /**
     * Generate a unique fingerprint for the request
     */
    protected function generateFingerprint(Request $request): string
    {
        // Use user ID, route, and key request parameters to create fingerprint
        $userId = $request->user()?->id ?? 'guest';
        $route = $request->route()?->getName() ?? $request->path();

        // Include specific route parameters that identify the resource
        $routeParams = [];
        if ($request->route()) {
            $routeParams = [
                'project' => $this->getRouteIdentifier($request->route('project')),
                'chapter' => $this->getRouteIdentifier($request->route('chapter')),
            ];
        }

        // Include specific request inputs that make this request unique
        $relevantInputs = $request->only([
            'title',
            'target_words',
            'operation',
        ]);

        $fingerprintData = [
            'user' => $userId,
            'route' => $route,
            'params' => $routeParams,
            'inputs' => $relevantInputs,
        ];

        return 'request_lock:'.md5(json_encode($fingerprintData));
    }

    /**
     * Safely extract an identifier from a route parameter that could be a model, array, or scalar.
     */
    protected function getRouteIdentifier($param): mixed
    {
        if (is_object($param) && method_exists($param, 'getAttribute')) {
            return $param->getAttribute('id');
        }

        if (is_array($param) && isset($param['id'])) {
            return $param['id'];
        }

        return $param;
    }
}
