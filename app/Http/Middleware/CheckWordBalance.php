<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if user has sufficient word balance for AI operations.
 *
 * Usage in routes:
 * Route::post('/generate')->middleware('check.words:3000');
 *
 * Or in controller:
 * $this->middleware('check.words:1000')->only(['generate']);
 */
class CheckWordBalance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  int  $requiredWords  Minimum words required
     */
    public function handle(Request $request, Closure $next, int $requiredWords = 0): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // If no specific amount required, just check they have some words
        if ($requiredWords === 0) {
            if ($user->word_balance <= 0) {
                return $this->insufficientBalanceResponse($user, 1);
            }
        } else {
            if (! $user->hasEnoughWords($requiredWords)) {
                return $this->insufficientBalanceResponse($user, $requiredWords);
            }
        }

        return $next($request);
    }

    /**
     * Return insufficient balance response
     */
    private function insufficientBalanceResponse($user, int $required): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Insufficient word balance',
            'error_code' => 'INSUFFICIENT_BALANCE',
            'data' => [
                'current_balance' => $user->word_balance,
                'required' => $required,
                'shortage' => max(0, $required - $user->word_balance),
            ],
        ], 402); // 402 Payment Required
    }
}
