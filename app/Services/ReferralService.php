<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ReferralEarning;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    public function __construct(
        private PaystackService $paystackService
    ) {}

    // =========================================================================
    // CONFIGURATION METHODS
    // =========================================================================

    /**
     * Check if referral system is enabled
     */
    public function isEnabled(): bool
    {
        $setting = SystemSetting::where('key', 'affiliate.enabled')->first()
            ?? SystemSetting::where('key', 'referral.enabled')->first();

        return $setting?->value['enabled'] ?? false;
    }

    /**
     * Get default commission percentage from settings
     */
    public function getDefaultCommissionRate(): float
    {
        $setting = SystemSetting::where('key', 'affiliate.commission_percentage')->first()
            ?? SystemSetting::where('key', 'referral.commission_percentage')->first();

        return (float) ($setting?->value['percentage'] ?? 10);
    }

    /**
     * Get minimum payment amount for commission (in kobo)
     */
    public function getMinimumPaymentAmount(): int
    {
        $setting = SystemSetting::where('key', 'affiliate.minimum_payment_amount')->first()
            ?? SystemSetting::where('key', 'referral.minimum_payment_amount')->first();

        return (int) ($setting?->value['amount'] ?? 100000);
    }

    /**
     * Get fee bearer setting
     */
    public function getFeeBearer(): string
    {
        $setting = SystemSetting::where('key', 'affiliate.fee_bearer')->first()
            ?? SystemSetting::where('key', 'referral.fee_bearer')->first();

        return $setting?->value['bearer'] ?? 'account';
    }

    /**
     * Get commission rate for a specific user (custom or default)
     */
    public function getCommissionRateForUser(User $referrer): float
    {
        return $referrer->getEffectiveCommissionRate() ?? $this->getDefaultCommissionRate();
    }

    // =========================================================================
    // COMMISSION CALCULATION
    // =========================================================================

    /**
     * Calculate commission for a payment amount
     */
    public function calculateCommission(int $paymentAmount, User $referrer): int
    {
        $rate = $this->getCommissionRateForUser($referrer);

        return (int) floor($paymentAmount * ($rate / 100));
    }

    // =========================================================================
    // REFERRAL CODE VALIDATION
    // =========================================================================

    /**
     * Validate a referral code
     * Returns the referrer user if valid, null otherwise
     */
    public function validateReferralCode(string $code): ?User
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $code = strtoupper(trim($code));

        $referrer = User::where('referral_code', $code)->first();

        if (! $referrer) {
            Log::info('Invalid referral code attempted', ['code' => $code]);

            return null;
        }

        // Check if referrer has bank setup (required to be a referrer)
        if (! $referrer->canReceiveCommissions()) {
            Log::info('Referral code for user without bank setup', [
                'code' => $code,
                'referrer_id' => $referrer->id,
            ]);

            return null;
        }

        return $referrer;
    }

    /**
     * Link a new user to their referrer
     */
    public function linkReferral(User $newUser, string $referralCode): bool
    {
        $referrer = $this->validateReferralCode($referralCode);

        if (! $referrer) {
            return false;
        }

        // Prevent self-referral
        if ($referrer->id === $newUser->id) {
            Log::warning('Self-referral attempt', [
                'user_id' => $newUser->id,
                'referral_code' => $referralCode,
            ]);

            return false;
        }

        // Prevent double referral
        if ($newUser->referred_by) {
            Log::info('User already has a referrer', [
                'user_id' => $newUser->id,
                'existing_referrer' => $newUser->referred_by,
            ]);

            return false;
        }

        $newUser->update([
            'referred_by' => $referrer->id,
            'referred_at' => now(),
        ]);

        Log::info('Referral link created', [
            'referee_id' => $newUser->id,
            'referrer_id' => $referrer->id,
        ]);

        return true;
    }

    // =========================================================================
    // PAYMENT QUALIFICATION
    // =========================================================================

    /**
     * Check if a payment qualifies for referral commission
     */
    public function paymentQualifiesForCommission(Payment $payment): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        // Check minimum amount
        if ($payment->amount < $this->getMinimumPaymentAmount()) {
            return false;
        }

        // Check if user was referred
        $user = $payment->user;
        if (! $user->wasReferred()) {
            return false;
        }

        // Check if referrer can receive commissions
        $referrer = $user->referrer;
        if (! $referrer || ! $referrer->canReceiveCommissions()) {
            return false;
        }

        // Check if earning already exists for this payment
        if ($payment->referralEarning()->exists()) {
            return false;
        }

        return true;
    }

    // =========================================================================
    // EARNING MANAGEMENT
    // =========================================================================

    /**
     * Create earning record for a payment
     */
    public function createEarningForPayment(Payment $payment): ?ReferralEarning
    {
        if (! $this->paymentQualifiesForCommission($payment)) {
            return null;
        }

        $referrer = $payment->user->referrer;
        $commissionAmount = $this->calculateCommission($payment->amount, $referrer);
        $commissionRate = $this->getCommissionRateForUser($referrer);

        return ReferralEarning::create([
            'referrer_id' => $referrer->id,
            'referee_id' => $payment->user_id,
            'payment_id' => $payment->id,
            'payment_amount' => $payment->amount,
            'commission_amount' => $commissionAmount,
            'commission_rate' => $commissionRate,
            'status' => ReferralEarning::STATUS_PENDING,
        ]);
    }

    /**
     * Mark earning as paid after successful payment verification
     */
    public function markEarningAsPaid(ReferralEarning $earning, ?array $paystackResponse = null): void
    {
        $earning->markAsPaid($paystackResponse);

        Log::info('Referral earning marked as paid', [
            'earning_id' => $earning->id,
            'referrer_id' => $earning->referrer_id,
            'commission_amount' => $earning->commission_amount,
        ]);
    }

    /**
     * Mark earning as refunded when payment is refunded
     */
    public function markEarningAsRefunded(Payment $payment): void
    {
        $earning = $payment->referralEarning;

        if ($earning) {
            $earning->markAsRefunded();

            Log::info('Referral earning marked as refunded', [
                'earning_id' => $earning->id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    // =========================================================================
    // DASHBOARD DATA
    // =========================================================================

    /**
     * Get referral dashboard data for a user
     */
    public function getDashboardData(User $user): array
    {
        // Ensure user has a referral code if they have bank setup
        if ($user->canReceiveCommissions() && ! $user->referral_code) {
            $user->generateReferralCode();
        }

        $earnings = ReferralEarning::where('referrer_id', $user->id);

        $totalEarned = (clone $earnings)->where('status', ReferralEarning::STATUS_PAID)->sum('commission_amount');
        $pendingEarnings = (clone $earnings)->where('status', ReferralEarning::STATUS_PENDING)->sum('commission_amount');
        $thisMonth = (clone $earnings)
            ->where('status', ReferralEarning::STATUS_PAID)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('commission_amount');

        return [
            'referral_code' => $user->referral_code,
            'referral_link' => $user->referral_code ? url('/register?ref='.$user->referral_code) : null,
            'bank_setup_complete' => $user->referral_bank_setup_complete,
            'has_custom_rate' => $user->hasCustomCommissionRate(),
            'commission_rate' => $this->getCommissionRateForUser($user),
            'stats' => [
                'total_referrals' => $user->referrals()->count(),
                'active_referrals' => $user->referrals()->whereHas('successfulPayments')->count(),
                'total_earned' => $totalEarned,
                'total_earned_formatted' => '₦'.number_format($totalEarned / 100, 0),
                'pending_earnings' => $pendingEarnings,
                'pending_earnings_formatted' => '₦'.number_format($pendingEarnings / 100, 0),
                'this_month' => $thisMonth,
                'this_month_formatted' => '₦'.number_format($thisMonth / 100, 0),
            ],
            'recent_earnings' => (clone $earnings)
                ->with('referee:id,name,email')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'referee_name' => $e->referee->name ?? 'Unknown',
                    'payment_amount' => $e->payment_amount,
                    'payment_formatted' => $e->formatted_payment,
                    'commission_amount' => $e->commission_amount,
                    'commission_formatted' => $e->formatted_commission,
                    'commission_rate' => $e->commission_rate,
                    'status' => $e->status,
                    'created_at' => $e->created_at->toISOString(),
                ]),
            'referrals' => $user->referrals()
                ->select('id', 'name', 'email', 'created_at')
                ->withCount(['successfulPayments'])
                ->latest()
                ->take(20)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'email' => $r->email,
                    'successful_payments_count' => $r->successful_payments_count,
                    'joined_at' => $r->created_at->toISOString(),
                ]),
        ];
    }

    // =========================================================================
    // ADMIN METHODS
    // =========================================================================

    /**
     * Get admin dashboard statistics
     */
    public function getAdminStats(): array
    {
        return [
            'total_referrers' => User::whereNotNull('referral_code')->count(),
            'total_referred_users' => User::whereNotNull('referred_by')->count(),
            'total_commissions_paid' => ReferralEarning::where('status', ReferralEarning::STATUS_PAID)->sum('commission_amount'),
            'pending_commissions' => ReferralEarning::where('status', ReferralEarning::STATUS_PENDING)->sum('commission_amount'),
            'this_month_commissions' => ReferralEarning::where('status', ReferralEarning::STATUS_PAID)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('commission_amount'),
            'failed_commissions' => ReferralEarning::where('status', ReferralEarning::STATUS_FAILED)->sum('commission_amount'),
        ];
    }

    /**
     * Update referral system settings
     */
    public function updateSettings(array $data): void
    {
        DB::transaction(function () use ($data) {
            if (isset($data['enabled'])) {
                SystemSetting::updateOrCreate(
                    ['key' => 'referral.enabled'],
                    ['value' => ['enabled' => (bool) $data['enabled']], 'type' => 'boolean', 'group' => 'referral']
                );
            }

            if (isset($data['commission_percentage'])) {
                SystemSetting::updateOrCreate(
                    ['key' => 'referral.commission_percentage'],
                    ['value' => ['percentage' => (float) $data['commission_percentage']], 'type' => 'integer', 'group' => 'referral']
                );
            }

            if (isset($data['minimum_payment_amount'])) {
                SystemSetting::updateOrCreate(
                    ['key' => 'referral.minimum_payment_amount'],
                    ['value' => ['amount' => (int) $data['minimum_payment_amount']], 'type' => 'integer', 'group' => 'referral']
                );
            }

            if (isset($data['fee_bearer'])) {
                SystemSetting::updateOrCreate(
                    ['key' => 'referral.fee_bearer'],
                    ['value' => ['bearer' => $data['fee_bearer']], 'type' => 'string', 'group' => 'referral']
                );
            }
        });

        Log::info('Referral settings updated', $data);
    }

    /**
     * Set custom commission rate for a user
     */
    public function setUserCommissionRate(User $user, float $rate): void
    {
        $user->update(['referral_commission_rate' => $rate]);

        Log::info('Custom commission rate set', [
            'user_id' => $user->id,
            'rate' => $rate,
        ]);
    }

    /**
     * Reset user to default commission rate
     */
    public function resetUserCommissionRate(User $user): void
    {
        $user->update(['referral_commission_rate' => null]);

        Log::info('User commission rate reset to default', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Get current referral settings
     */
    public function getSettings(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'commission_percentage' => $this->getDefaultCommissionRate(),
            'minimum_payment_amount' => $this->getMinimumPaymentAmount(),
            'fee_bearer' => $this->getFeeBearer(),
        ];
    }
}
