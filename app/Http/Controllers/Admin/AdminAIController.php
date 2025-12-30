<?php

namespace App\Http\Controllers\Admin;

use App\Events\AIProvisioningUpdated;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\OpenAIBillingSnapshot;
use App\Models\OpenAICreditSetting;
use App\Services\AIProvisioningService;
use App\Services\OpenAIBillingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAIController extends Controller
{
    public function index(AIProvisioningService $provisioningService)
    {
        $days = request()->integer('days', 30);
        $days = in_array($days, [7, 30, 90]) ? $days : 30;

        $metrics = $provisioningService->getMetrics();
        $profitability = $provisioningService->getProfitabilityMetrics($days);
        $usageTrends = $provisioningService->getUsageTrends($days);
        $liabilityBreakdown = $provisioningService->getUserLiabilityBreakdown();
        $modelBreakdown = $provisioningService->getModelBreakdown($days);

        $latestSnapshot = OpenAIBillingSnapshot::latest('fetched_at')->first();

        // Get live balance info (from database settings + API costs)
        $creditSettings = OpenAICreditSetting::current();

        return Inertia::render('Admin/AI/Index', [
            'metrics' => $metrics,
            'profitability' => $profitability,
            'usageTrends' => $usageTrends,
            'liabilityBreakdown' => $liabilityBreakdown,
            'modelBreakdown' => $modelBreakdown,
            'billingSnapshot' => $latestSnapshot,
            'modelPricing' => config('ai.model_pricing'),
            'selectedDays' => $days,
            'creditSettings' => [
                'initial_balance' => $creditSettings->initial_balance,
                'balance_set_at' => $creditSettings->balance_set_at?->toIso8601String(),
                'notes' => $creditSettings->notes,
            ],
            'balanceInfo' => $metrics['balance_info'] ?? null,
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
        $days = request()->integer('days', 30);
        $days = in_array($days, [7, 30, 90]) ? $days : 30;

        $snapshot = $billingService->fetchAndStore();
        $metrics = $provisioningService->getMetrics();
        $profitability = $provisioningService->getProfitabilityMetrics($days);
        $usageTrends = $provisioningService->getUsageTrends($days);
        $liabilityBreakdown = $provisioningService->getUserLiabilityBreakdown();
        $modelBreakdown = $provisioningService->getModelBreakdown($days);

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
                $modelBreakdown,
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
            'profitability' => $profitability,
            'usageTrends' => $usageTrends,
            'liabilityBreakdown' => $liabilityBreakdown,
            'modelBreakdown' => $modelBreakdown,
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

    /**
     * Update OpenAI credit balance settings.
     */
    public function updateCreditBalance(Request $request, AIProvisioningService $provisioningService)
    {
        $validated = $request->validate([
            'initial_balance' => 'required|numeric|min:0|max:10000',
            'notes' => 'nullable|string|max:255',
        ]);

        $settings = OpenAICreditSetting::current();
        $settings->setBalance($validated['initial_balance'], $validated['notes'] ?? null);

        // Get updated metrics with the new balance
        $metrics = $provisioningService->getMetrics();

        return response()->json([
            'success' => true,
            'message' => 'Credit balance updated successfully.',
            'settings' => [
                'initial_balance' => $settings->initial_balance,
                'balance_set_at' => $settings->balance_set_at?->toIso8601String(),
                'notes' => $settings->notes,
            ],
            'balance_info' => $metrics['balance_info'],
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get current credit settings.
     */
    public function getCreditSettings(AIProvisioningService $provisioningService)
    {
        $settings = OpenAICreditSetting::current();
        $balanceInfo = $provisioningService->getLiveBalance();

        return response()->json([
            'success' => true,
            'settings' => [
                'initial_balance' => $settings->initial_balance,
                'balance_set_at' => $settings->balance_set_at?->toIso8601String(),
                'notes' => $settings->notes,
            ],
            'balance_info' => $balanceInfo,
        ]);
    }
}
