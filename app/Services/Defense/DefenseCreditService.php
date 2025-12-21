<?php

namespace App\Services\Defense;

use App\Models\DefenseSession;
use App\Models\User;
use App\Models\WordTransaction;
use App\Services\WordBalanceService;

class DefenseCreditService
{
    private WordBalanceService $wordBalanceService;

    public function __construct(WordBalanceService $wordBalanceService)
    {
        $this->wordBalanceService = $wordBalanceService;
    }

    public function estimateSessionCost(string $mode, int $estimatedMinutes): array
    {
        $base = config('pricing.estimation.defense_session_base', 200);
        $feedback = config('pricing.estimation.defense_feedback_generation', 500);
        $multiplier = $mode === 'audio'
            ? config('pricing.estimation.defense_audio_multiplier', 2.0)
            : 1.0;

        return [
            'base_words' => (int) ceil($base * $multiplier),
            'feedback_words' => (int) ceil($feedback * $multiplier),
            'estimated_minutes' => $estimatedMinutes,
            'mode' => $mode,
        ];
    }

    public function hasEnoughCredits(User $user, string $mode): bool
    {
        $minimum = config('pricing.minimum_balance.defense', 500);
        if ($mode === 'audio') {
            $minimum = (int) ceil($minimum * config('pricing.estimation.defense_audio_multiplier', 2.0));
        }

        return $user->hasEnoughWords($minimum);
    }

    public function deductSessionBase(User $user, DefenseSession $session): void
    {
        $base = (int) config('pricing.estimation.defense_session_base', 0);
        if ($base <= 0) {
            return;
        }

        $this->deductWords($user, $session, $base, 'Defense session start');
    }

    public function deductFeedbackCost(User $user, DefenseSession $session): void
    {
        $feedback = (int) config('pricing.estimation.defense_feedback_generation', 0);
        if ($feedback <= 0) {
            return;
        }

        $this->deductWords($user, $session, $feedback, 'Defense session feedback');
    }

    public function deductForTextExchange(?User $user, ?DefenseSession $session, string $aiResponse, string $description): void
    {
        if (! $user) {
            return;
        }

        $wordCount = str_word_count(strip_tags($aiResponse));
        if ($wordCount <= 0) {
            return;
        }

        $this->deductWords($user, $session, $wordCount, $description, [
            'word_count' => $wordCount,
        ]);
    }

    private function deductWords(User $user, ?DefenseSession $session, int $words, string $description, array $metadata = []): void
    {
        $referenceId = $session?->id;
        $transaction = $this->wordBalanceService->deductForGeneration(
            $user,
            $words,
            $description,
            WordTransaction::REF_DEFENSE,
            $referenceId,
            array_merge($metadata, [
                'session_id' => $referenceId,
            ])
        );

        if ($session) {
            $session->increment('words_consumed', $words);
        }
    }
}
