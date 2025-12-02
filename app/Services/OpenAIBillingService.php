<?php

namespace App\Services;

use App\Models\OpenAIBillingSnapshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIBillingService
{
    public function fetchAndStore(): ?OpenAIBillingSnapshot
    {
        $credit = $this->fetchCreditGrants();

        if (! $credit) {
            return null;
        }

        $usage = $this->fetchUsage();

        return OpenAIBillingSnapshot::create([
            'granted_usd' => $credit['granted'] ?? 0,
            'used_usd' => $credit['used'] ?? 0,
            'available_usd' => $credit['available'] ?? 0,
            'expires_at' => $credit['expires_at'] ?? null,
            'period_start' => $usage['start'] ?? null,
            'period_end' => $usage['end'] ?? null,
            'raw' => [
                'credit' => $credit['raw'] ?? null,
                'usage' => $usage['raw'] ?? null,
            ],
            'fetched_at' => now(),
        ]);
    }

    public function fetchCreditGrants(): ?array
    {
        try {
            $response = $this->client()->get('/v1/dashboard/billing/credit_grants');
            $json = $response->json();

            if (! $response->successful() || ! $json) {
                Log::warning('OpenAI billing credit_grants failed', ['status' => $response->status(), 'body' => $json]);

                return null;
            }

            return [
                'granted' => $json['total_granted'] ?? 0,
                'used' => $json['total_used'] ?? 0,
                'available' => $json['total_available'] ?? 0,
                'expires_at' => isset($json['expires_at']) ? \Carbon\Carbon::parse($json['expires_at']) : null,
                'raw' => $json,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch OpenAI billing credit grants', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function fetchUsage(?string $startDate = null, ?string $endDate = null): ?array
    {
        $start = $startDate ?? now()->startOfMonth()->toDateString();
        $end = $endDate ?? now()->toDateString();

        try {
            $response = $this->client()->get('/v1/dashboard/billing/usage', [
                'start_date' => $start,
                'end_date' => $end,
            ]);

            $json = $response->json();

            if (! $response->successful() || ! $json) {
                Log::warning('OpenAI billing usage failed', ['status' => $response->status(), 'body' => $json]);

                return null;
            }

            return [
                'start' => $start,
                'end' => $end,
                'total_usage' => $json['total_usage'] ?? 0,
                'raw' => $json,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch OpenAI billing usage', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function client()
    {
        return Http::withToken(config('openai.api_key'))
            ->baseUrl(config('ai.billing_base', 'https://api.openai.com'))
            ->acceptJson()
            ->timeout(15);
    }
}
