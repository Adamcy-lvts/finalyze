<?php

namespace App\Http\Controllers;

use App\Models\ReferralBankAccount;
use App\Services\PaystackService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService,
        private PaystackService $paystackService
    ) {}

    /**
     * Show referral dashboard
     */
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Referrals/Index', [
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

        // Create Paystack subaccount
        $result = $this->paystackService->createSubaccount(
            businessName: $user->name.' - Referral Partner',
            bankCode: $request->bank_code,
            accountNumber: $request->account_number,
            description: 'Referral commission account'
        );

        if (! $result['status']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to setup bank account',
            ], 400);
        }

        // Deactivate existing bank accounts
        ReferralBankAccount::where('user_id', $user->id)->update(['is_active' => false]);

        // Create new bank account record
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

        // Update user with subaccount code and generate referral code
        $user->update([
            'paystack_subaccount_code' => $result['data']['subaccount_code'],
            'referral_bank_setup_complete' => true,
        ]);

        // Generate referral code now that bank is setup
        $referralCode = $user->generateReferralCode();

        return response()->json([
            'success' => true,
            'message' => 'Bank account setup complete',
            'referral_code' => $referralCode,
            'referral_link' => url('/register?ref='.$referralCode),
        ]);
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
            ->paginate(20);

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
}
