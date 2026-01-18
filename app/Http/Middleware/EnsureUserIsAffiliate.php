<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AffiliateService;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAffiliate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! app(AffiliateService::class)->isEnabled()) {
            return redirect()->route('dashboard')->with('error', 'Affiliate program is currently disabled.');
        }

        if (! $user->isAffiliate()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
