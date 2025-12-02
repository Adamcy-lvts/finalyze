<?php

namespace App\Http\Controllers\Admin;

use App\Events\AIProvisioningUpdated;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AIUsageDaily;
use App\Models\OpenAIBillingSnapshot;
use App\Services\AIProvisioningService;
use App\Services\OpenAIBillingService;
use Inertia\Inertia;

class AdminAIController extends Controller
{
    public function index(AIProvisioningService $provisioningService)
    {
        $metrics = $provisioningService->getMetrics();

        $usageByModel = collect($this->usageByModel());

        $latestSnapshot = OpenAIBillingSnapshot::latest('fetched_at')->first();

        return Inertia::render('Admin/AI/Index', [
            'metrics' => $metrics,
            'usageByModel' => $usageByModel,
            'billingSnapshot' => $latestSnapshot,
        ]);
    }

    public function queue()
    {
        return Inertia::render('Admin/AI/Queue');
    }

    public function failures()
    {
        return Inertia::render('Admin/AI/Failures');
    }

    public function retry($generation)
    {
        return response()->json(['status' => 'ok']);
    }

    public function resetCircuit($service)
    {
        return response()->json(['status' => 'ok']);
    }

    public function refresh(OpenAIBillingService $billingService, AIProvisioningService $provisioningService)
    {
        $snapshot = $billingService->fetchAndStore();
        $metrics = $provisioningService->getMetrics();
        $usageByModel = $this->usageByModel();

        if (config('ai.alerts_enabled') && in_array($metrics['status'] ?? 'safe', ['warning', 'critical'])) {
            AdminNotification::create([
                'type' => 'ai_provisioning',
                'title' => $metrics['status'] === 'critical' ? 'OpenAI balance critical' : 'OpenAI balance warning',
                'message' => sprintf(
                    'Available: $%s | Liability: %s tokens | Runway: %s days',
                    $metrics['snapshot']?->available_usd ?? 0,
                    number_format($metrics['liability_tokens'] ?? 0),
                    $metrics['runway_days'] ?? 'n/a'
                ),
                'severity' => $metrics['status'],
                'data' => $metrics,
                'is_read' => false,
            ]);
        }

        if ($snapshot) {
            broadcast(new AIProvisioningUpdated(
                $metrics,
                $usageByModel,
                [
                    'available_usd' => $snapshot->available_usd,
                    'used_usd' => $snapshot->used_usd,
                    'granted_usd' => $snapshot->granted_usd,
                    'period_start' => optional($snapshot->period_start)->toDateString(),
                    'period_end' => optional($snapshot->period_end)->toDateString(),
                ]
            ));
        }

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'usageByModel' => $usageByModel,
            'snapshot' => $snapshot,
        ]);
    }

    public function metrics(AIProvisioningService $provisioningService)
    {
        $metrics = $provisioningService->getMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    private function usageByModel()
    {
        return AIUsageDaily::query()
            ->where('date', '>=', now()->subDays(7))
            ->selectRaw('model, SUM(prompt_tokens) as prompt_tokens, SUM(completion_tokens) as completion_tokens, SUM(total_tokens) as total_tokens')
            ->groupBy('model')
            ->orderByDesc('total_tokens')
            ->get()
            ->map(function ($row) {
                return [
                    'model' => $row->model ?? 'unknown',
                    'input_tokens' => (int) $row->prompt_tokens,
                    'output_tokens' => (int) $row->completion_tokens,
                ];
            })
            ->values()
            ->toArray();
    }
}
