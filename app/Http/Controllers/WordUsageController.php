<?php

namespace App\Http\Controllers;

use App\Services\WordBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for tracking word usage from AI generation features.
 *
 * This is called after successful AI operations to deduct words from user balance.
 */
class WordUsageController extends Controller
{
    public function __construct(
        private WordBalanceService $wordBalanceService
    ) {}

    /**
     * Record word usage after an AI operation
     */
    public function recordUsage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'words' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
            'reference_type' => 'required|string|max:50',
            'reference_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        $user = $request->user();

        try {
            // Check if user has enough words
            if (! $user->hasEnoughWords($validated['words'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient word balance',
                    'error_code' => 'INSUFFICIENT_BALANCE',
                    'data' => [
                        'balance' => $user->word_balance,
                        'required' => $validated['words'],
                    ],
                ], 402);
            }

            // Record the usage
            $transaction = $this->wordBalanceService->deductForGeneration(
                $user,
                $validated['words'],
                $validated['description'],
                $validated['reference_type'],
                $validated['reference_id'] ?? null,
                $validated['metadata'] ?? null
            );

            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Word usage recorded',
                'data' => [
                    'words_used' => $validated['words'],
                    'new_balance' => $user->word_balance,
                    'transaction_id' => $transaction->id,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to record word usage', [
                'user_id' => $user->id,
                'words' => $validated['words'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record word usage',
            ], 500);
        }
    }

    /**
     * Pre-authorize words before an AI operation (optional, for complex workflows)
     *
     * This doesn't deduct words, just checks if user has enough and reserves them
     * in a temporary hold. Actual deduction happens via recordUsage after success.
     */
    public function preAuthorize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estimated_words' => 'required|integer|min:1',
            'action' => 'required|string|max:100',
        ]);

        $user = $request->user();

        $check = $this->wordBalanceService->canPerform($user, $validated['estimated_words']);

        if (! $check['can_proceed']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient word balance for this action',
                'error_code' => 'INSUFFICIENT_BALANCE',
                'data' => $check,
            ], 402);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sufficient balance available',
            'data' => [
                'balance' => $user->word_balance,
                'estimated_cost' => $validated['estimated_words'],
                'remaining_after' => $user->word_balance - $validated['estimated_words'],
            ],
        ]);
    }

    /**
     * Refund words for a failed operation
     */
    public function refund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'words' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
            'reference_type' => 'required|string|max:50',
            'reference_id' => 'nullable|integer',
        ]);

        $user = $request->user();

        try {
            $transaction = $this->wordBalanceService->refundForFailedGeneration(
                $user,
                $validated['words'],
                $validated['description'],
                $validated['reference_type'],
                $validated['reference_id'] ?? null
            );

            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Words refunded successfully',
                'data' => [
                    'words_refunded' => $validated['words'],
                    'new_balance' => $user->word_balance,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to refund words', [
                'user_id' => $user->id,
                'words' => $validated['words'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund',
            ], 500);
        }
    }

    /**
     * Get word usage estimates for different actions
     */
    public function estimates(): JsonResponse
    {
        return response()->json([
            'estimates' => [
                'chapter_per_1000_target' => 1100, // 10% buffer
                'ai_suggestion' => 200,
                'chat_response' => 500,
                'defense_questions' => 1000,
                'expand_text' => 300,
                'rephrase_text' => 150,
            ],
        ]);
    }
}
