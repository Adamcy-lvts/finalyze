<?php

namespace App\Listeners;

use App\Services\WordBalanceService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

/**
 * Listener to credit signup bonus words when a new user registers.
 */
class CreditSignupBonus
{
    public function __construct(
        private WordBalanceService $wordBalanceService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $user = $event->user;

        try {
            $credited = $this->wordBalanceService->creditSignupBonus($user);

            if ($credited) {
                $bonusWords = config('pricing.signup_bonus_words', 5000);
                Log::info('Signup bonus credited', [
                    'user_id' => $user->id,
                    'words' => $bonusWords,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to credit signup bonus', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
