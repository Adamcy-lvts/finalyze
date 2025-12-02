<?php

namespace App\Services;

use App\Models\AIUsageDaily;
use App\Models\OpenAIBillingSnapshot;
use App\Models\User;
use Carbon\Carbon;

class AIProvisioningService
{
    public function getMetrics(): array
    {
        $wordsToTokens = config('ai.words_to_tokens_factor', 1.5);
        $latestSnapshot = OpenAIBillingSnapshot::latest('fetched_at')->first();

        $liabilityTokens = $this->getLiabilityTokens($wordsToTokens);
        $pricePer1k = $this->getWeightedPricePer1kTokens();
        $walletTokens = $this->convertWalletUsdToTokens($latestSnapshot?->available_usd ?? 0, $pricePer1k);

        $avgDailyTokens7 = $this->getAverageDailyTokens(7);
        $avgDailyTokens30 = $this->getAverageDailyTokens(30);
        $runwayDays = $this->calculateRunwayDays($walletTokens, $avgDailyTokens7 ?: $avgDailyTokens30);

        $status = $this->determineStatus($latestSnapshot?->available_usd ?? 0, $walletTokens, $liabilityTokens, $runwayDays);

        return [
            'snapshot' => $latestSnapshot,
            'liability_tokens' => (int) $liabilityTokens,
            'wallet_tokens' => (int) $walletTokens,
            'price_per_1k_tokens_usd' => $pricePer1k,
            'avg_daily_tokens_7' => $avgDailyTokens7,
            'avg_daily_tokens_30' => $avgDailyTokens30,
            'runway_days' => $runwayDays,
            'status' => $status,
        ];
    }

    private function getLiabilityTokens(float $wordsToTokens): float
    {
        $totalWords = User::query()->sum('word_balance');

        return $totalWords * $wordsToTokens;
    }

    private function getWeightedPricePer1kTokens(): float
    {
        $pricing = config('ai.model_pricing', []);

        $since = Carbon::today()->subDays(30);
        $usage = AIUsageDaily::where('date', '>=', $since)->get();

        if ($usage->isEmpty()) {
            return $this->fallbackPricePer1k();
        }

        $totalTokens = $usage->sum('total_tokens');
        $weightedCost = 0;

        foreach ($usage as $row) {
            $modelPricing = $pricing[$row->model] ?? null;
            if (! $modelPricing) {
                continue;
            }

            $avgModelPrice = (($modelPricing['prompt'] ?? 0) + ($modelPricing['completion'] ?? 0)) / 2;
            $weightedCost += $avgModelPrice * ($row->total_tokens / 1000);
        }

        if ($totalTokens === 0) {
            return $this->fallbackPricePer1k();
        }

        return $weightedCost / ($totalTokens / 1000);
    }

    private function fallbackPricePer1k(): float
    {
        $default = config('ai.model_pricing.gpt-4o-mini', ['prompt' => 0.00015, 'completion' => 0.0006]);

        return (($default['prompt'] ?? 0) + ($default['completion'] ?? 0)) / 2;
    }

    private function convertWalletUsdToTokens(float $walletUsd, float $pricePer1k): float
    {
        if ($pricePer1k <= 0) {
            return 0;
        }

        return ($walletUsd / $pricePer1k) * 1000;
    }

    private function getAverageDailyTokens(int $days): int
    {
        $since = Carbon::today()->subDays($days);

        $usage = AIUsageDaily::where('date', '>=', $since)->get();
        if ($usage->isEmpty()) {
            return 0;
        }

        $totalTokens = $usage->sum('total_tokens');

        return (int) round($totalTokens / $days);
    }

    private function calculateRunwayDays(float $walletTokens, int $avgDailyTokens): ?int
    {
        if ($avgDailyTokens <= 0) {
            return null;
        }

        return (int) floor($walletTokens / $avgDailyTokens);
    }

    private function determineStatus(float $walletUsd, float $walletTokens, float $liabilityTokens, ?int $runwayDays): string
    {
        $thresholds = config('ai.thresholds');

        $critical = $walletUsd <= ($thresholds['wallet_usd_low'] ?? 5)
            || ($runwayDays !== null && $runwayDays <= ($thresholds['runway_days_critical'] ?? 3));

        $warning = ($walletTokens > 0 && $liabilityTokens / max($walletTokens, 1) >= ($thresholds['liability_ratio'] ?? 1.2))
            || ($runwayDays !== null && $runwayDays <= ($thresholds['runway_days_warning'] ?? 7));

        if ($critical) {
            return 'critical';
        }

        if ($warning) {
            return 'warning';
        }

        return 'safe';
    }
}
