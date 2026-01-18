<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralEarning;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService
    ) {}

    /**
     * Admin referral dashboard with stats and settings
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Referrals/Index', [
            'stats' => $this->referralService->getAdminStats(),
            'settings' => $this->referralService->getSettings(),
            'topReferrers' => $this->getTopReferrers(),
            'recentEarnings' => $this->getRecentEarnings(),
        ]);
    }

    /**
     * Update global referral settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'commission_percentage' => 'sometimes|numeric|min:0|max:100',
            'minimum_payment_amount' => 'sometimes|integer|min:0',
            'fee_bearer' => 'sometimes|string|in:account,subaccount,all,all-proportional',
        ]);

        $this->referralService->updateSettings($data);

        return response()->json([
            'success' => true,
            'message' => 'Referral settings updated',
            'settings' => $this->referralService->getSettings(),
        ]);
    }

    /**
     * List all referrers with their commission rates
     */
    public function users(Request $request): Response
    {
        $query = User::whereNotNull('referral_code')
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

        $referrers = $query->orderByDesc('total_earned')
            ->paginate(20)
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'referral_code' => $user->referral_code,
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

        return Inertia::render('Admin/Referrals/Users', [
            'referrers' => $referrers,
            'defaultRate' => $this->referralService->getDefaultCommissionRate(),
        ]);
    }

    /**
     * Set custom commission rate for a user
     */
    public function updateUserRate(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        $this->referralService->setUserCommissionRate($user, $data['commission_rate']);

        return response()->json([
            'success' => true,
            'message' => 'Commission rate updated for '.$user->name,
            'user' => [
                'id' => $user->id,
                'commission_rate' => $user->fresh()->referral_commission_rate,
                'has_custom_rate' => true,
            ],
        ]);
    }

    /**
     * Reset user to default commission rate
     */
    public function resetUserRate(User $user): JsonResponse
    {
        $this->referralService->resetUserCommissionRate($user);

        return response()->json([
            'success' => true,
            'message' => 'Commission rate reset to default for '.$user->name,
            'user' => [
                'id' => $user->id,
                'commission_rate' => null,
                'has_custom_rate' => false,
            ],
        ]);
    }

    /**
     * Get earnings history with filters
     */
    public function earnings(Request $request): Response
    {
        $query = ReferralEarning::with([
            'referrer:id,name,email,referral_code',
            'referee:id,name,email',
            'payment:id,paystack_reference,created_at',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('referrer_id')) {
            $query->where('referrer_id', $request->referrer_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $earnings = $query->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($e) => [
                'id' => $e->id,
                'referrer' => [
                    'id' => $e->referrer->id,
                    'name' => $e->referrer->name,
                    'email' => $e->referrer->email,
                    'referral_code' => $e->referrer->referral_code,
                ],
                'referee' => [
                    'id' => $e->referee->id,
                    'name' => $e->referee->name,
                    'email' => $e->referee->email,
                ],
                'payment_amount' => $e->payment_amount,
                'payment_formatted' => $e->formatted_payment,
                'commission_amount' => $e->commission_amount,
                'commission_formatted' => $e->formatted_commission,
                'commission_rate' => $e->commission_rate,
                'status' => $e->status,
                'created_at' => $e->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Referrals/Earnings', [
            'earnings' => $earnings,
            'filters' => [
                'status' => $request->status,
                'referrer_id' => $request->referrer_id,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
            ],
        ]);
    }

    /**
     * Get top referrers by earnings
     */
    private function getTopReferrers(int $limit = 10): array
    {
        return User::whereNotNull('referral_code')
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

    /**
     * Get recent earnings for dashboard
     */
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
