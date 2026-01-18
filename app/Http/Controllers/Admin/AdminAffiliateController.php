<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralEarning;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAffiliateController extends Controller
{
    public function __construct(
        private AffiliateService $affiliateService,
        private ReferralService $referralService
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Affiliates/Index', [
            'stats' => $this->getStats(),
            'settings' => $this->affiliateService->getSettings(),
            'topAffiliates' => $this->getTopAffiliates(),
            'recentEarnings' => $this->getRecentEarnings(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'registration_open' => 'sometimes|boolean',
            'commission_percentage' => 'sometimes|numeric|min:0|max:100',
            'minimum_payment_amount' => 'sometimes|integer|min:0',
            'fee_bearer' => 'sometimes|string|in:account,subaccount,all,all-proportional',
            'promo_popup_enabled' => 'sometimes|boolean',
            'promo_popup_delay_days' => 'sometimes|integer|min:0',
        ]);

        $this->affiliateService->updateSettings($data);

        return response()->json([
            'success' => true,
            'message' => 'Affiliate settings updated',
            'settings' => $this->affiliateService->getSettings(),
        ]);
    }

    public function list(Request $request): Response
    {
        $query = User::role('affiliate')
            ->with('referralBankAccount:id,user_id,bank_name,account_name')
            ->withCount(['referrals', 'referralEarnings as total_earnings_count'])
            ->withSum(['referralEarnings as total_earned' => function ($q) {
                $q->where('status', ReferralEarning::STATUS_PAID);
            }], 'commission_amount');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        $affiliates = $query->orderByDesc('total_earned')
            ->paginate(20)
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'referral_code' => $user->referral_code,
                'affiliate_type' => $user->affiliate_is_pure ? 'pure' : 'dual',
                'affiliate_status' => $user->affiliate_status,
                'commission_rate' => $user->referral_commission_rate,
                'has_custom_rate' => $user->hasCustomCommissionRate(),
                'effective_rate' => $this->referralService->getCommissionRateForUser($user),
                'total_referrals' => $user->referrals_count,
                'total_earned' => $user->total_earned ?? 0,
                'total_earned_formatted' => '₦'.number_format(($user->total_earned ?? 0) / 100, 0),
                'bank_name' => $user->referralBankAccount?->bank_name,
                'account_name' => $user->referralBankAccount?->account_name,
                'created_at' => $user->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Affiliates/List', [
            'affiliates' => $affiliates,
            'defaultRate' => $this->affiliateService->getDefaultCommissionRate(),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'affiliate_notes' => 'nullable|string',
        ]);

        if (array_key_exists('commission_rate', $data)) {
            if ($data['commission_rate'] === null) {
                $this->referralService->resetUserCommissionRate($user);
            } else {
                $this->referralService->setUserCommissionRate($user, (float) $data['commission_rate']);
            }
        }

        if (array_key_exists('affiliate_notes', $data)) {
            $user->update(['affiliate_notes' => $data['affiliate_notes']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Affiliate updated',
        ]);
    }

    public function resetRate(User $user): JsonResponse
    {
        $this->referralService->resetUserCommissionRate($user);

        return response()->json([
            'success' => true,
            'message' => 'Commission rate reset to default',
        ]);
    }

    private function getStats(): array
    {
        return [
            'total_affiliates' => User::role('affiliate')->count(),
            'total_referred_users' => User::whereNotNull('referred_by')->count(),
            'total_commissions_paid' => ReferralEarning::where('status', ReferralEarning::STATUS_PAID)->sum('commission_amount'),
            'pending_commissions' => ReferralEarning::where('status', ReferralEarning::STATUS_PENDING)->sum('commission_amount'),
            'this_month_commissions' => ReferralEarning::where('status', ReferralEarning::STATUS_PAID)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('commission_amount'),
            'failed_commissions' => ReferralEarning::where('status', ReferralEarning::STATUS_FAILED)->sum('commission_amount'),
            'pending_requests' => User::where('affiliate_status', 'pending')->count(),
        ];
    }

    private function getTopAffiliates(int $limit = 10): array
    {
        return User::role('affiliate')
            ->withCount('referrals')
            ->withSum(['referralEarnings as total_earned' => function ($q) {
                $q->where('status', ReferralEarning::STATUS_PAID);
            }], 'commission_amount')
            ->having('total_earned', '>', 0)
            ->orderByDesc('total_earned')
            ->limit($limit)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'referral_code' => $user->referral_code,
                'referrals_count' => $user->referrals_count,
                'total_earned' => $user->total_earned ?? 0,
                'total_earned_formatted' => '₦'.number_format(($user->total_earned ?? 0) / 100, 0),
            ])
            ->toArray();
    }

    private function getRecentEarnings(int $limit = 10): array
    {
        return ReferralEarning::with(['referrer:id,name', 'referee:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'referrer_name' => $e->referrer->name,
                'referee_name' => $e->referee->name,
                'commission_formatted' => $e->formatted_commission,
                'status' => $e->status,
                'created_at' => $e->created_at->toISOString(),
            ])
            ->toArray();
    }
}
