<?php

namespace App\Services;

use App\Models\AIUsageDaily;
use App\Models\OpenAICreditSetting;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

class AIProvisioningService
{
    public function getMetrics(): array
    {
        $wordsToTokens = config('ai.words_to_tokens_factor', 1.5);

        // Get live balance (initial balance - costs since balance was set)
        $balanceInfo = $this->getLiveBalance();
        $availableUsd = $balanceInfo['available_usd'];

        $liabilityTokens = $this->getLiabilityTokens($wordsToTokens);
        $pricePer1k = $this->getWeightedPricePer1kTokens();
        $walletTokens = $this->convertWalletUsdToTokens($availableUsd, $pricePer1k);

        $avgDailyTokens7 = $this->getAverageDailyTokens(7);
        $avgDailyTokens30 = $this->getAverageDailyTokens(30);
        $runwayDays = $this->calculateRunwayDays($walletTokens, $avgDailyTokens7 ?: $avgDailyTokens30);

        $status = $this->determineStatus($availableUsd, $walletTokens, $liabilityTokens, $runwayDays);

        return [
            'balance_info' => $balanceInfo,
            'liability_tokens' => (int) $liabilityTokens,
            'wallet_tokens' => (int) $walletTokens,
            'price_per_1k_tokens_usd' => $pricePer1k,
            'avg_daily_tokens_7' => $avgDailyTokens7,
            'avg_daily_tokens_30' => $avgDailyTokens30,
            'runway_days' => $runwayDays,
            'status' => $status,
        ];
    }

    /**
     * Calculate live balance: initial_balance - costs_since_balance_set
     */
    public function getLiveBalance(): array
    {
        $settings = OpenAICreditSetting::current();
        $initialBalance = $settings->initial_balance;
        $balanceSetAt = $settings->balance_set_at ?? now()->subYear();

        // Fetch costs from OpenAI API since balance was set
        $costsSinceSet = 0;
        $costSource = 'local';

        if (config('ai.admin_api_key')) {
            $billingService = app(OpenAIBillingService::class);
            $costs = $billingService->fetchCosts($balanceSetAt->toDateString(), now()->toDateString());

            if ($costs && isset($costs['total_cost']) && empty($costs['fallback'])) {
                $costsSinceSet = $costs['total_cost'];
                $costSource = 'openai_api';
            }
        }

        // Fall back to local data if no API data
        if ($costsSinceSet === 0 && $costSource === 'local') {
            $costsSinceSet = AIUsageDaily::where('date', '>=', $balanceSetAt->toDateString())
                ->sum('cost_usd');
        }

        $availableUsd = max(0, $initialBalance - $costsSinceSet);

        return [
            'initial_balance' => round($initialBalance, 4),
            'costs_since_set' => round($costsSinceSet, 4),
            'available_usd' => round($availableUsd, 4),
            'balance_set_at' => $balanceSetAt->toIso8601String(),
            'cost_source' => $costSource,
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

        // Try OpenAI API first
        if (config('ai.admin_api_key')) {
            $billingService = app(OpenAIBillingService::class);
            $usage = $billingService->fetchUsage($since->toDateString(), now()->toDateString());

            if ($usage && ! empty($usage['total_tokens'])) {
                return (int) round($usage['total_tokens'] / $days);
            }
        }

        // Fall back to local data
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

    /**
     * Get profitability metrics for the specified period.
     *
     * @return array{revenue_ngn: float, revenue_usd: float, cost_usd: float, profit_usd: float, margin_percent: float, period_days: int, cost_source: string}
     */
    public function getProfitabilityMetrics(int $days = 30): array
    {
        $since = Carbon::today()->subDays($days);

        // Revenue from successful payments (convert kobo to NGN)
        $revenueNGN = Payment::successful()
            ->where('paid_at', '>=', $since)
            ->sum('amount') / 100;

        // Convert NGN to USD using configurable rate
        $exchangeRate = config('pricing.ngn_to_usd_rate', 1500);
        $revenueUSD = $exchangeRate > 0 ? $revenueNGN / $exchangeRate : 0;

        // Try to get actual costs from OpenAI API first
        $costSource = 'local';
        $costUSD = 0;

        if (config('ai.admin_api_key')) {
            $billingService = app(OpenAIBillingService::class);
            $costs = $billingService->fetchCosts($since->toDateString(), now()->toDateString());

            if ($costs && ! empty($costs['total_cost']) && empty($costs['fallback'])) {
                $costUSD = $costs['total_cost'];
                $costSource = 'openai_api';
            }
        }

        // Fall back to local AI usage logs if no OpenAI data
        if ($costUSD === 0) {
            $costUSD = AIUsageDaily::where('date', '>=', $since)->sum('cost_usd');
        }

        $profit = $revenueUSD - $costUSD;
        $margin = $revenueUSD > 0 ? ($profit / $revenueUSD) * 100 : 0;

        return [
            'revenue_ngn' => round($revenueNGN, 2),
            'revenue_usd' => round($revenueUSD, 2),
            'cost_usd' => round($costUSD, 4),
            'profit_usd' => round($profit, 2),
            'margin_percent' => round($margin, 1),
            'period_days' => $days,
            'cost_source' => $costSource,
        ];
    }

    /**
     * Get daily usage trends for charts.
     * Prefers OpenAI API data when admin key is configured.
     *
     * @return array<int, array{date: string, tokens: int, cost: float, source: string}>
     */
    public function getUsageTrends(int $days = 30): array
    {
        $since = Carbon::today()->subDays($days);

        // Try OpenAI API first
        if (config('ai.admin_api_key')) {
            $billingService = app(OpenAIBillingService::class);
            $apiTrends = $billingService->fetchDailyUsageTrends($since->toDateString(), now()->toDateString());

            if ($apiTrends && ! empty($apiTrends)) {
                return array_map(fn ($item) => [
                    'date' => $item['date'],
                    'tokens' => $item['tokens'],
                    'cost' => 0, // Cost comes from Costs API separately
                    'source' => 'openai_api',
                ], $apiTrends);
            }
        }

        // Fall back to local data
        return AIUsageDaily::query()
            ->where('date', '>=', $since)
            ->selectRaw('date, SUM(total_tokens) as tokens, SUM(cost_usd) as cost')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->format('M d'),
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 4),
                'source' => 'local',
            ])
            ->toArray();
    }

    /**
     * Get user liability breakdown showing total exposure.
     *
     * @return array{users_with_balance: int, total_words: int, total_tokens: int, top_users: \Illuminate\Support\Collection}
     */
    public function getUserLiabilityBreakdown(): array
    {
        $wordsToTokens = config('ai.words_to_tokens_factor', 1.5);

        $usersWithBalance = User::where('word_balance', '>', 0)->count();
        $totalWords = (int) User::sum('word_balance');
        $totalTokens = (int) ($totalWords * $wordsToTokens);

        $topUsers = User::where('word_balance', '>', 0)
            ->orderByDesc('word_balance')
            ->limit(10)
            ->get(['id', 'name', 'email', 'word_balance']);

        return [
            'users_with_balance' => $usersWithBalance,
            'total_words' => $totalWords,
            'total_tokens' => $totalTokens,
            'top_users' => $topUsers,
        ];
    }

    /**
     * Get token usage breakdown by model with costs.
     * Prefers OpenAI API data when admin key is configured.
     *
     * @return array<int, array{model: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, cost_usd: float, pricing: array|null, source: string}>
     */
    public function getModelBreakdown(int $days = 30): array
    {
        $pricing = config('ai.model_pricing', []);
        $since = Carbon::today()->subDays($days);

        // Try OpenAI API first
        if (config('ai.admin_api_key')) {
            $billingService = app(OpenAIBillingService::class);
            $apiModels = $billingService->fetchModelBreakdown($since->toDateString(), now()->toDateString());

            if ($apiModels && ! empty($apiModels)) {
                return array_map(fn ($item) => [
                    'model' => $item['model'] ?? 'unknown',
                    'prompt_tokens' => $item['prompt_tokens'],
                    'completion_tokens' => $item['completion_tokens'],
                    'total_tokens' => $item['total_tokens'],
                    'cost_usd' => 0, // Would need to calculate or get from costs API
                    'pricing' => $pricing[$item['model']] ?? null,
                    'source' => 'openai_api',
                ], $apiModels);
            }
        }

        // Fall back to local data
        return AIUsageDaily::query()
            ->where('date', '>=', $since)
            ->selectRaw('model, SUM(prompt_tokens) as prompt_tokens, SUM(completion_tokens) as completion_tokens, SUM(total_tokens) as total_tokens, SUM(cost_usd) as cost_usd')
            ->groupBy('model')
            ->orderByDesc('total_tokens')
            ->get()
            ->map(function ($row) use ($pricing) {
                return [
                    'model' => $row->model ?? 'unknown',
                    'prompt_tokens' => (int) $row->prompt_tokens,
                    'completion_tokens' => (int) $row->completion_tokens,
                    'total_tokens' => (int) $row->total_tokens,
                    'cost_usd' => round((float) $row->cost_usd, 4),
                    'pricing' => $pricing[$row->model] ?? null,
                    'source' => 'local',
                ];
            })
            ->toArray();
    }
}
