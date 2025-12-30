<?php

namespace App\Services;

use App\Models\OpenAIBillingSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIBillingService
{
    /**
     * Fetch costs and usage from OpenAI and store a snapshot.
     */
    public function fetchAndStore(): ?OpenAIBillingSnapshot
    {
        $costs = $this->fetchCosts();
        $usage = $this->fetchUsage();

        // Get manual credit balance from config (since API doesn't expose it)
        $manualBalance = (float) config('ai.manual_credit_balance', 0);

        // Calculate total spent from costs API
        $totalSpent = $costs['total_cost'] ?? 0;

        // If we have a manual balance, calculate available
        $available = $manualBalance > 0 ? max(0, $manualBalance - $totalSpent) : 0;

        return OpenAIBillingSnapshot::create([
            'granted_usd' => $manualBalance,
            'used_usd' => $totalSpent,
            'available_usd' => $available,
            'expires_at' => null, // Not available via official API
            'period_start' => $costs['start'] ?? null,
            'period_end' => $costs['end'] ?? null,
            'raw' => [
                'costs' => $costs['raw'] ?? null,
                'usage' => $usage['raw'] ?? null,
            ],
            'fetched_at' => now(),
        ]);
    }

    /**
     * Fetch organization costs from the official API.
     * Endpoint: /v1/organization/costs
     */
    public function fetchCosts(?string $startDate = null, ?string $endDate = null): ?array
    {
        $start = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : now();

        try {
            $response = $this->adminClient()->get('/v1/organization/costs', [
                'start_time' => $start->timestamp,
                'end_time' => $end->timestamp,
                'bucket_width' => '1d',
                'limit' => 31,
            ]);

            $json = $response->json();

            if (! $response->successful()) {
                Log::warning('OpenAI costs API failed', [
                    'status' => $response->status(),
                    'body' => $json,
                ]);

                return $this->fallbackToCosts($startDate, $endDate);
            }

            // Sum up costs from all buckets
            $totalCost = 0;
            $costsByModel = [];

            foreach ($json['data'] ?? [] as $bucket) {
                foreach ($bucket['results'] ?? [] as $result) {
                    // OpenAI Costs API returns amount in dollars (not cents)
                    $cost = (float) ($result['amount']['value'] ?? 0);
                    $totalCost += $cost;

                    $lineItem = $result['line_item'] ?? 'unknown';
                    $costsByModel[$lineItem] = ($costsByModel[$lineItem] ?? 0) + $cost;
                }
            }

            return [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'total_cost' => round($totalCost, 4),
                'costs_by_model' => $costsByModel,
                'raw' => $json,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch OpenAI costs', ['error' => $e->getMessage()]);

            return $this->fallbackToCosts($startDate, $endDate);
        }
    }

    /**
     * Fetch completions usage from the official API (grouped by model).
     * Endpoint: /v1/organization/usage/completions
     * Handles pagination to get all results.
     */
    public function fetchUsage(?string $startDate = null, ?string $endDate = null): ?array
    {
        $start = $startDate ? Carbon::parse($startDate) : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : now();

        try {
            $totalInputTokens = 0;
            $totalOutputTokens = 0;
            $usageByModel = [];
            $allRaw = [];
            $page = null;
            $maxPages = 10; // Safety limit to prevent infinite loops
            $pageCount = 0;

            do {
                $params = [
                    'start_time' => $start->timestamp,
                    'end_time' => $end->timestamp,
                    'bucket_width' => '1d',
                    'group_by' => ['model'],
                    'limit' => 100,
                ];

                if ($page) {
                    $params['page'] = $page;
                }

                $response = $this->adminClient()->get('/v1/organization/usage/completions', $params);
                $json = $response->json();

                if (! $response->successful()) {
                    Log::warning('OpenAI usage API failed', [
                        'status' => $response->status(),
                        'body' => $json,
                    ]);

                    return $this->fallbackToUsage($startDate, $endDate);
                }

                $allRaw[] = $json;

                // Aggregate usage by model from this page
                foreach ($json['data'] ?? [] as $bucket) {
                    foreach ($bucket['results'] ?? [] as $result) {
                        $model = $result['model'] ?? 'unknown';
                        $inputTokens = $result['input_tokens'] ?? 0;
                        $outputTokens = $result['output_tokens'] ?? 0;

                        $totalInputTokens += $inputTokens;
                        $totalOutputTokens += $outputTokens;

                        if (! isset($usageByModel[$model])) {
                            $usageByModel[$model] = ['input_tokens' => 0, 'output_tokens' => 0];
                        }
                        $usageByModel[$model]['input_tokens'] += $inputTokens;
                        $usageByModel[$model]['output_tokens'] += $outputTokens;
                    }
                }

                // Check for next page
                $hasMore = $json['has_more'] ?? false;
                $page = $json['next_page'] ?? null;
                $pageCount++;

            } while ($hasMore && $page && $pageCount < $maxPages);

            return [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'total_input_tokens' => $totalInputTokens,
                'total_output_tokens' => $totalOutputTokens,
                'total_tokens' => $totalInputTokens + $totalOutputTokens,
                'usage_by_model' => $usageByModel,
                'pages_fetched' => $pageCount,
                'raw' => $allRaw,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch OpenAI usage', ['error' => $e->getMessage()]);

            return $this->fallbackToUsage($startDate, $endDate);
        }
    }

    /**
     * Fetch daily usage trends from OpenAI API.
     * Returns daily token counts for charts.
     * Handles pagination to get all results.
     */
    public function fetchDailyUsageTrends(?string $startDate = null, ?string $endDate = null): ?array
    {
        $start = $startDate ? Carbon::parse($startDate) : now()->subDays(30);
        $end = $endDate ? Carbon::parse($endDate) : now();

        try {
            $dailyData = [];
            $page = null;
            $maxPages = 10; // Safety limit
            $pageCount = 0;

            do {
                $params = [
                    'start_time' => $start->timestamp,
                    'end_time' => $end->timestamp,
                    'bucket_width' => '1d',
                    'limit' => 100,
                ];

                if ($page) {
                    $params['page'] = $page;
                }

                $response = $this->adminClient()->get('/v1/organization/usage/completions', $params);
                $json = $response->json();

                if (! $response->successful()) {
                    return null;
                }

                // Aggregate by day from this page
                foreach ($json['data'] ?? [] as $bucket) {
                    $date = Carbon::createFromTimestamp($bucket['start_time'])->format('Y-m-d');
                    $dayTokens = 0;

                    foreach ($bucket['results'] ?? [] as $result) {
                        $dayTokens += ($result['input_tokens'] ?? 0) + ($result['output_tokens'] ?? 0);
                    }

                    if (! isset($dailyData[$date])) {
                        $dailyData[$date] = 0;
                    }
                    $dailyData[$date] += $dayTokens;
                }

                // Check for next page
                $hasMore = $json['has_more'] ?? false;
                $page = $json['next_page'] ?? null;
                $pageCount++;

            } while ($hasMore && $page && $pageCount < $maxPages);

            // Sort by date and format
            ksort($dailyData);

            return array_map(fn ($date, $tokens) => [
                'date' => Carbon::parse($date)->format('M d'),
                'raw_date' => $date,
                'tokens' => $tokens,
            ], array_keys($dailyData), array_values($dailyData));
        } catch (\Exception $e) {
            Log::error('Failed to fetch daily usage trends', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fetch model breakdown from OpenAI API.
     * Returns token counts per model.
     */
    public function fetchModelBreakdown(?string $startDate = null, ?string $endDate = null): ?array
    {
        $usage = $this->fetchUsage($startDate, $endDate);

        if (! $usage || empty($usage['usage_by_model'])) {
            return null;
        }

        $models = [];
        foreach ($usage['usage_by_model'] as $model => $data) {
            $models[] = [
                'model' => $model,
                'prompt_tokens' => $data['input_tokens'],
                'completion_tokens' => $data['output_tokens'],
                'total_tokens' => $data['input_tokens'] + $data['output_tokens'],
            ];
        }

        // Sort by total tokens descending
        usort($models, fn ($a, $b) => $b['total_tokens'] <=> $a['total_tokens']);

        return $models;
    }

    /**
     * HTTP client configured with Admin API key for official endpoints.
     */
    private function adminClient()
    {
        $adminKey = config('ai.admin_api_key');

        if (! $adminKey) {
            Log::warning('OpenAI Admin API key not configured. Set OPENAI_ADMIN_API_KEY in .env');
        }

        return Http::withToken($adminKey ?: config('openai.api_key'))
            ->baseUrl(config('ai.billing_base', 'https://api.openai.com'))
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Fallback to deprecated credit_grants endpoint if official API fails.
     * This provides backward compatibility for users who haven't migrated.
     */
    private function fallbackToCosts(?string $startDate, ?string $endDate): ?array
    {
        try {
            $response = $this->legacyClient()->get('/v1/dashboard/billing/credit_grants');
            $json = $response->json();

            if (! $response->successful() || ! $json) {
                return null;
            }

            return [
                'start' => $startDate ?? now()->startOfMonth()->toDateString(),
                'end' => $endDate ?? now()->toDateString(),
                'total_cost' => $json['total_used'] ?? 0,
                'granted' => $json['total_granted'] ?? 0,
                'available' => $json['total_available'] ?? 0,
                'costs_by_model' => [],
                'raw' => $json,
                'fallback' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Legacy credit_grants fallback failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fallback to deprecated usage endpoint.
     */
    private function fallbackToUsage(?string $startDate, ?string $endDate): ?array
    {
        $start = $startDate ?? now()->startOfMonth()->toDateString();
        $end = $endDate ?? now()->toDateString();

        try {
            $response = $this->legacyClient()->get('/v1/dashboard/billing/usage', [
                'start_date' => $start,
                'end_date' => $end,
            ]);

            $json = $response->json();

            if (! $response->successful() || ! $json) {
                return null;
            }

            return [
                'start' => $start,
                'end' => $end,
                'total_usage' => $json['total_usage'] ?? 0,
                'raw' => $json,
                'fallback' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Legacy usage fallback failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * HTTP client for legacy/deprecated endpoints using regular API key.
     */
    private function legacyClient()
    {
        return Http::withToken(config('openai.api_key'))
            ->baseUrl(config('ai.billing_base', 'https://api.openai.com'))
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * Helper method to get current credit balance.
     * Since the official API doesn't expose this, we use manual config
     * or calculate from the latest snapshot if available.
     */
    public function getCurrentBalance(): float
    {
        // Check manual config first
        $manualBalance = (float) config('ai.manual_credit_balance', 0);

        if ($manualBalance > 0) {
            return $manualBalance;
        }

        // Fall back to latest snapshot
        $snapshot = OpenAIBillingSnapshot::latest('fetched_at')->first();

        return $snapshot?->available_usd ?? 0;
    }
}
