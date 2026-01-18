<?php

namespace App\Http\Controllers;

use App\Models\ReferralBankAccount;
use App\Services\PaystackService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function __construct(
        private ReferralService $referralService,
        private PaystackService $paystackService
    ) {}

    /**
     * Show affiliate dashboard
     */
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Affiliate/Dashboard', [
            'data' => $this->referralService->getDashboardData($user),
            'banks' => $this->paystackService->getBanks(),
            'isEnabled' => $this->referralService->isEnabled(),
        ]);
    }

    /**
     * Verify bank account with Paystack
     */
    public function verifyBankAccount(Request $request): JsonResponse
    {
        $request->validate([
            'bank_code' => 'required|string',
            'account_number' => 'required|string|size:10',
        ]);

        $result = $this->paystackService->resolveAccountNumber(
            $request->account_number,
            $request->bank_code
        );

        if (! $result['status']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not verify account',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'account_name' => $result['data']['account_name'],
        ]);
    }

    /**
     * Setup bank account for receiving commissions
     */
    public function setupBankAccount(Request $request): JsonResponse
    {
        $request->validate([
            'bank_code' => 'required|string',
            'bank_name' => 'required|string',
            'account_number' => 'required|string|size:10',
            'account_name' => 'required|string',
        ]);

        $user = auth()->user();

        $result = $this->paystackService->createSubaccount(
            businessName: $user->name.' - Affiliate Partner',
            bankCode: $request->bank_code,
            accountNumber: $request->account_number,
            description: 'Affiliate commission account'
        );

        if (! $result['status']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to setup bank account',
            ], 400);
        }

        ReferralBankAccount::where('user_id', $user->id)->update(['is_active' => false]);

        ReferralBankAccount::create([
            'user_id' => $user->id,
            'bank_code' => $request->bank_code,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'subaccount_code' => $result['data']['subaccount_code'],
            'is_verified' => true,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $user->update([
            'paystack_subaccount_code' => $result['data']['subaccount_code'],
            'referral_bank_setup_complete' => true,
        ]);

        $referralCode = $user->generateReferralCode();

        return response()->json([
            'success' => true,
            'message' => 'Bank account setup complete',
            'referral_code' => $referralCode,
            'referral_link' => url('/register?ref='.$referralCode),
        ]);
    }

    /**
     * Earnings page
     */
    public function earningsPage(): Response
    {
        return Inertia::render('Affiliate/Earnings');
    }

    /**
     * Get earnings history (paginated)
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = auth()->user();

        $earnings = $user->referralEarnings()
            ->with('referee:id,name,email', 'payment:id,amount,created_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($earning) => [
                'id' => $earning->id,
                'referee' => $earning->referee ? [
                    'name' => $earning->referee->name,
                    'email' => $earning->referee->email,
                ] : null,
                'payment_amount' => $earning->payment_amount,
                'commission_amount' => $earning->commission_amount,
                'commission_rate' => $earning->commission_rate,
                'status' => $earning->status,
                'created_at' => $earning->created_at?->toISOString(),
            ]);

        return response()->json([
            'earnings' => $earnings->items(),
            'pagination' => [
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
                'total' => $earnings->total(),
                'per_page' => $earnings->perPage(),
            ],
        ]);
    }

    /**
     * Referrals page
     */
    public function referralsPage(): Response
    {
        return Inertia::render('Affiliate/Referrals');
    }

    /**
     * List referred users (paginated)
     */
    public function referrals(Request $request): JsonResponse
    {
        $user = auth()->user();

        $referrals = $user->referrals()
            ->select('id', 'name', 'email', 'created_at')
            ->withCount(['successfulPayments'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($referral) => [
                'id' => $referral->id,
                'name' => $referral->name,
                'email' => $referral->email,
                'successful_payments_count' => $referral->successful_payments_count,
                'created_at' => $referral->created_at?->toISOString(),
            ]);

        return response()->json([
            'referrals' => $referrals->items(),
            'pagination' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'total' => $referrals->total(),
                'per_page' => $referrals->perPage(),
            ],
        ]);
    }
}
