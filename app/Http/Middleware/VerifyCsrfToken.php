<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected $except = [
        'api/chapters/*/verify-citations',
        'api/*',
        'projects/*/chapters/*/chat/stream',
    ];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     */
    protected function inExceptArray($request): bool
    {
        Log::info('=== CSRF MIDDLEWARE DEBUG ===', [
            'request_path' => $request->path(),
            'request_url' => $request->url(),
            'except_patterns' => $this->except,
            'checking_exemption' => true,
        ]);

        $result = parent::inExceptArray($request);

        Log::info('=== CSRF EXEMPTION RESULT ===', [
            'request_path' => $request->path(),
            'is_exempt' => $result,
        ]);

        return $result;
    }
}
