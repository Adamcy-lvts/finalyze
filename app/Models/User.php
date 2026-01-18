<?php

namespace App\Models;

use App\Events\WordBalanceUpdated;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Impersonate, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'word_balance',
        'total_words_purchased',
        'total_words_used',
        'bonus_words_received',
        'received_signup_bonus',
        'is_banned',
        'banned_at',
        'ban_reason',
        'banned_by',
        'last_active_at',
        // Referral fields
        'referral_code',
        'referred_by',
        'referral_commission_rate',
        'paystack_subaccount_code',
        'referral_bank_setup_complete',
        'referred_at',
        'affiliate_status',
        'affiliate_requested_at',
        'affiliate_approved_at',
        'affiliate_notes',
        'affiliate_is_pure',
        'affiliate_promo_dismissed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'word_balance' => 'integer',
            'total_words_purchased' => 'integer',
            'total_words_used' => 'integer',
            'bonus_words_received' => 'integer',
            'received_signup_bonus' => 'boolean',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'last_active_at' => 'datetime',
            // Referral casts
            'referral_commission_rate' => 'decimal:2',
            'referral_bank_setup_complete' => 'boolean',
            'referred_at' => 'datetime',
            // Affiliate casts
            'affiliate_requested_at' => 'datetime',
            'affiliate_approved_at' => 'datetime',
            'affiliate_is_pure' => 'boolean',
            'affiliate_promo_dismissed_at' => 'datetime',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function activeProject()
    {
        return $this->hasOne(Project::class)->where('is_active', true)->latest();
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function wordTransactions(): HasMany
    {
        return $this->hasMany(WordTransaction::class);
    }

    public function successfulPayments(): HasMany
    {
        return $this->payments()->where('status', Payment::STATUS_SUCCESS);
    }

    /**
     * The user who referred this user
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * Users referred by this user
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * Earnings from referrals
     */
    public function referralEarnings(): HasMany
    {
        return $this->hasMany(ReferralEarning::class, 'referrer_id');
    }

    /**
     * Bank account for referral payouts (active one)
     */
    public function referralBankAccount(): HasOne
    {
        return $this->hasOne(ReferralBankAccount::class)->where('is_active', true);
    }

    /**
     * All bank accounts for referral payouts
     */
    public function referralBankAccounts(): HasMany
    {
        return $this->hasMany(ReferralBankAccount::class);
    }

    // =========================================================================
    // WORD BALANCE METHODS
    // =========================================================================

    /**
     * Check if user has enough words
     */
    public function hasEnoughWords(int $required): bool
    {
        return $this->word_balance >= $required;
    }

    /**
     * Add words to balance (purchase or bonus)
     */
    public function addWords(int $words, bool $isPurchase = true): void
    {
        DB::transaction(function () use ($words, $isPurchase) {
            $this->increment('word_balance', $words);

            if ($isPurchase) {
                $this->increment('total_words_purchased', $words);
            } else {
                $this->increment('bonus_words_received', $words);
            }
        });

        // Refresh to get updated values and broadcast
        $this->refresh();
        try {
            WordBalanceUpdated::dispatch($this, $isPurchase ? 'purchase' : 'bonus');
        } catch (\Throwable $e) {
            Log::warning('Word balance broadcast failed', [
                'user_id' => $this->id,
                'reason' => $isPurchase ? 'purchase' : 'bonus',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deduct words from balance
     *
     * @throws \Exception If insufficient balance
     */
    public function deductWords(int $words): void
    {
        if (! $this->hasEnoughWords($words)) {
            throw new \Exception("Insufficient word balance. Required: {$words}, Available: {$this->word_balance}");
        }

        DB::transaction(function () use ($words) {
            $this->decrement('word_balance', $words);
            $this->increment('total_words_used', $words);
        });

        // Refresh to get updated values and broadcast
        $this->refresh();
        try {
            WordBalanceUpdated::dispatch($this, 'usage');
        } catch (\Throwable $e) {
            Log::warning('Word balance broadcast failed', [
                'user_id' => $this->id,
                'reason' => 'usage',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Refund words back to balance
     */
    public function refundWords(int $words): void
    {
        DB::transaction(function () use ($words) {
            $this->increment('word_balance', $words);
            $this->decrement('total_words_used', $words);
        });

        // Refresh to get updated values and broadcast
        $this->refresh();
        try {
            WordBalanceUpdated::dispatch($this, 'refund');
        } catch (\Throwable $e) {
            Log::warning('Word balance broadcast failed', [
                'user_id' => $this->id,
                'reason' => 'refund',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Credit signup bonus if not already received
     */
    public function creditSignupBonus(): bool
    {
        if ($this->received_signup_bonus) {
            return false;
        }

        $bonusWords = config('pricing.signup_bonus_words', 5000);

        DB::transaction(function () use ($bonusWords) {
            $this->addWords($bonusWords, false);
            $this->update(['received_signup_bonus' => true]);

            WordTransaction::recordBonus(
                $this,
                $bonusWords,
                'Signup bonus - Welcome to the platform!',
                WordTransaction::REF_SIGNUP
            );
        });

        return true;
    }

    /**
     * Get word balance data for frontend
     */
    public function getWordBalanceData(): array
    {
        // Guard against drift between counters by falling back to derived totals
        $totalTracked = $this->total_words_purchased + $this->bonus_words_received;
        $totalDerived = $this->word_balance + $this->total_words_used;
        $totalAllocated = max($totalTracked, $totalDerived);

        $percentageUsed = $totalAllocated > 0
            ? round(($this->total_words_used / $totalAllocated) * 100, 1)
            : 0;
        $percentageUsed = max(0, min(100, $percentageUsed));

        return [
            'balance' => $this->word_balance,
            'formatted_balance' => number_format($this->word_balance),
            'total_purchased' => $this->total_words_purchased,
            'total_used' => $this->total_words_used,
            'bonus_received' => $this->bonus_words_received,
            'total_allocated' => $totalAllocated,
            'percentage_used' => $percentageUsed,
            'percentage_remaining' => max(0, 100 - $percentageUsed),
        ];
    }

    /**
     * Get recent word transactions
     */
    public function getRecentTransactions(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->wordTransactions()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get formatted word balance
     */
    public function getFormattedWordBalanceAttribute(): string
    {
        return number_format($this->word_balance);
    }

    /**
     * Check if user has any words
     */
    public function getHasWordsAttribute(): bool
    {
        return $this->word_balance > 0;
    }

    /**
     * Get total amount spent (in Naira)
     */
    public function getTotalSpentAttribute(): float
    {
        return $this->successfulPayments()->sum('amount') / 100;
    }

    // =========================================================================
    // AFFILIATE METHODS
    // =========================================================================

    public function isAffiliate(): bool
    {
        return $this->hasRole('affiliate');
    }

    public function isPureAffiliate(): bool
    {
        return $this->isAffiliate() && $this->affiliate_is_pure;
    }

    public function hasDualAccess(): bool
    {
        return $this->isAffiliate() && ! $this->affiliate_is_pure;
    }

    public function canRequestAffiliateAccess(): bool
    {
        if ($this->isAffiliate()) {
            return false;
        }

        return $this->affiliate_status !== 'pending';
    }

    public function hasPendingAffiliateRequest(): bool
    {
        return $this->affiliate_status === 'pending';
    }

    // =========================================================================
    // REFERRAL METHODS
    // =========================================================================

    /**
     * Generate unique referral code for user
     */
    public function generateReferralCode(): string
    {
        if ($this->referral_code) {
            return $this->referral_code;
        }

        do {
            // Format: 2 letters from name + 6 random alphanumeric
            $namePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $this->name), 0, 2));
            if (strlen($namePrefix) < 2) {
                $namePrefix = str_pad($namePrefix, 2, 'X');
            }
            $code = $namePrefix.strtoupper(Str::random(6));
        } while (User::where('referral_code', $code)->exists());

        $this->update(['referral_code' => $code]);

        return $code;
    }

    /**
     * Check if user can receive referral commissions
     */
    public function canReceiveCommissions(): bool
    {
        return $this->isAffiliate()
            && $this->referral_bank_setup_complete
            && $this->paystack_subaccount_code
            && $this->referralBankAccount()->exists();
    }

    /**
     * Check if user has a custom commission rate
     */
    public function hasCustomCommissionRate(): bool
    {
        return $this->referral_commission_rate !== null;
    }

    /**
     * Get effective commission rate (custom or null for default)
     */
    public function getEffectiveCommissionRate(): ?float
    {
        return $this->referral_commission_rate;
    }

    /**
     * Get referral stats for dashboard
     */
    public function getReferralStats(): array
    {
        $earnings = $this->referralEarnings();

        return [
            'total_referrals' => $this->referrals()->count(),
            'active_referrals' => $this->referrals()->whereHas('successfulPayments')->count(),
            'total_earned' => $earnings->clone()->where('status', ReferralEarning::STATUS_PAID)->sum('commission_amount'),
            'pending_earnings' => $earnings->clone()->where('status', ReferralEarning::STATUS_PENDING)->sum('commission_amount'),
            'referral_code' => $this->referral_code,
            'bank_setup_complete' => $this->referral_bank_setup_complete,
            'has_custom_rate' => $this->hasCustomCommissionRate(),
            'commission_rate' => $this->referral_commission_rate,
        ];
    }

    /**
     * Check if this user was referred
     */
    public function wasReferred(): bool
    {
        return $this->referred_by !== null;
    }
}
