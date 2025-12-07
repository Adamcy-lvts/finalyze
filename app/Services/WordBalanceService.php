<?php

namespace App\Services;

use App\Events\WordBalanceUpdated;
use App\Models\Payment;
use App\Models\User;
use App\Models\WordTransaction;
use App\Notifications\LowBalanceWarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing user word balances
 *
 * Handles all word credit/debit operations with proper transaction logging
 */
class WordBalanceService
{
    /**
     * Credit words from a successful payment
     */
    public function creditFromPayment(User $user, Payment $payment): void
    {
        DB::transaction(function () use ($user, $payment) {
            // Add words to user balance
            $user->addWords($payment->words_purchased, true);

            // Refresh to get updated balance
            $user->refresh();

            // Record transaction
            WordTransaction::recordPurchase(
                $user,
                $payment->words_purchased,
                $payment,
                "Purchased {$payment->wordPackage->name} - {$payment->words_purchased} words"
            );

            Log::info('Words credited from payment', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'words' => $payment->words_purchased,
                'new_balance' => $user->word_balance,
            ]);
        });
    }

    /**
     * Credit signup bonus to new user
     */
    public function creditSignupBonus(User $user): bool
    {
        if ($user->received_signup_bonus) {
            return false;
        }

        $bonusWords = config('pricing.signup_bonus_words', 5000);

        DB::transaction(function () use ($user, $bonusWords) {
            $user->addWords($bonusWords, false);
            $user->update(['received_signup_bonus' => true]);
            $user->refresh();

            WordTransaction::recordBonus(
                $user,
                $bonusWords,
                "Welcome bonus - {$bonusWords} free words!",
                WordTransaction::REF_SIGNUP
            );

            Log::info('Signup bonus credited', [
                'user_id' => $user->id,
                'words' => $bonusWords,
            ]);
        });

        return true;
    }

    /**
     * Deduct words for AI generation usage
     *
     * Uses pessimistic locking to prevent race conditions when multiple
     * concurrent requests attempt to deduct words simultaneously.
     *
     * @throws \Exception If insufficient balance
     */
    public function deductForGeneration(
        User $user,
        int $wordsUsed,
        string $description,
        string $referenceType,
        ?int $referenceId = null,
        ?array $metadata = null
    ): WordTransaction {
        $transaction = DB::transaction(function () use ($user, $wordsUsed, $description, $referenceType, $referenceId, $metadata) {
            // Lock the user row for update to prevent race conditions
            // This ensures that concurrent requests will be serialized
            $lockedUser = User::where('id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedUser) {
                throw new \Exception('User not found');
            }

            // Check balance on the locked user record
            if ($lockedUser->word_balance < $wordsUsed) {
                throw new \Exception("Insufficient word balance. Required: {$wordsUsed}, Available: {$lockedUser->word_balance}");
            }

            // Deduct words using atomic decrement
            $lockedUser->decrement('word_balance', $wordsUsed);
            $lockedUser->increment('total_words_used', $wordsUsed);

            // Refresh to get updated balance
            $lockedUser->refresh();

            // Record transaction
            $transaction = WordTransaction::recordUsage(
                $lockedUser,
                $wordsUsed,
                $description,
                $referenceType,
                $referenceId,
                $metadata
            );

            Log::info('Words deducted for generation', [
                'user_id' => $lockedUser->id,
                'words' => $wordsUsed,
                'reference' => "{$referenceType}:{$referenceId}",
                'new_balance' => $lockedUser->word_balance,
            ]);

            // Update the passed-in user object to reflect changes
            $user->word_balance = $lockedUser->word_balance;
            $user->total_words_used = $lockedUser->total_words_used;

            return $transaction;
        });

        // Broadcast balance update for real-time UI updates
        $user->refresh(); // Ensure we have fresh data
        WordBalanceUpdated::dispatch($user, 'usage');

        // Check for low balance after transaction completes
        $this->checkAndNotifyLowBalance($user);

        return $transaction;
    }

    /**
     * Refund words for failed generation
     */
    public function refundForFailedGeneration(
        User $user,
        int $wordsToRefund,
        string $description,
        string $referenceType,
        ?int $referenceId = null
    ): WordTransaction {
        return DB::transaction(function () use ($user, $wordsToRefund, $description, $referenceType, $referenceId) {
            // Refund words
            $user->refundWords($wordsToRefund);
            $user->refresh();

            // Record transaction
            $transaction = WordTransaction::recordRefund(
                $user,
                $wordsToRefund,
                $description,
                $referenceType,
                $referenceId
            );

            Log::info('Words refunded for failed generation', [
                'user_id' => $user->id,
                'words' => $wordsToRefund,
                'reference' => "{$referenceType}:{$referenceId}",
                'new_balance' => $user->word_balance,
            ]);

            return $transaction;
        });
    }

    /**
     * Check if user can perform an action requiring words
     */
    public function canPerform(User $user, int $estimatedWords): array
    {
        $hasEnough = $user->hasEnoughWords($estimatedWords);

        return [
            'can_proceed' => $hasEnough,
            'balance' => $user->word_balance,
            'required' => $estimatedWords,
            'shortage' => $hasEnough ? 0 : $estimatedWords - $user->word_balance,
        ];
    }

    /**
     * Estimate words needed for chapter generation
     */
    public function estimateChapterWords(int $targetWordCount): int
    {
        // Add 10% buffer for safety
        return (int) ceil($targetWordCount * 1.1);
    }

    /**
     * Estimate words for AI suggestion
     */
    public function estimateSuggestionWords(): int
    {
        return 200; // Average suggestion length
    }

    /**
     * Estimate words for chat response
     */
    public function estimateChatWords(): int
    {
        return 500; // Average chat response
    }

    /**
     * Estimate words for defense questions
     */
    public function estimateDefenseWords(): int
    {
        return 1000; // Questions + explanations
    }

    /**
     * Get user's word usage statistics
     */
    public function getUsageStats(User $user, ?int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $transactions = $user->wordTransactions()
            ->where('created_at', '>=', $startDate)
            ->get();

        $usage = $transactions->where('type', WordTransaction::TYPE_USAGE);
        $purchases = $transactions->where('type', WordTransaction::TYPE_PURCHASE);

        // Group usage by reference type
        $usageByType = $usage->groupBy('reference_type')->map(function ($items) {
            return [
                'count' => $items->count(),
                'words' => abs($items->sum('words')),
            ];
        });

        return [
            'period_days' => $days,
            'total_used' => abs($usage->sum('words')),
            'total_purchased' => $purchases->sum('words'),
            'transaction_count' => $transactions->count(),
            'usage_breakdown' => $usageByType,
            'average_daily_usage' => $days > 0 ? round(abs($usage->sum('words')) / $days) : 0,
        ];
    }

    /**
     * Admin: Manually adjust user balance
     */
    public function adminAdjust(
        User $user,
        int $words,
        string $reason,
        int $adminId
    ): WordTransaction {
        return DB::transaction(function () use ($user, $words, $reason, $adminId) {
            if ($words > 0) {
                $user->addWords($words, false);
            } else {
                $user->decrement('word_balance', abs($words));
            }

            $user->refresh();

            $transaction = WordTransaction::recordAdjustment(
                $user,
                $words,
                "Admin adjustment: {$reason}",
                $adminId
            );

            Log::info('Admin word balance adjustment', [
                'user_id' => $user->id,
                'admin_id' => $adminId,
                'words' => $words,
                'reason' => $reason,
                'new_balance' => $user->word_balance,
            ]);

            return $transaction;
        });
    }

    /**
     * Check user's balance and send low balance warning if needed
     */
    private function checkAndNotifyLowBalance(User $user): void
    {
        $lowBalanceThreshold = config('pricing.low_balance_threshold', 1000);

        // Only notify if balance is below threshold
        if ($user->word_balance > $lowBalanceThreshold) {
            return;
        }

        // Check if user was notified recently (within last 24 hours) to avoid spam
        $recentNotification = $user->notifications()
            ->where('type', LowBalanceWarning::class)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($recentNotification) {
            return;
        }

        // Send low balance warning
        $user->notify(new LowBalanceWarning($user->word_balance, $lowBalanceThreshold));

        Log::info('Low balance warning sent', [
            'user_id' => $user->id,
            'balance' => $user->word_balance,
            'threshold' => $lowBalanceThreshold,
        ]);
    }
}
