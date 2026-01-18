<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'package_id',
        'amount',
        'currency',
        'words_purchased',
        'paystack_reference',
        'paystack_access_code',
        'paystack_transaction_id',
        'status',
        'channel',
        'card_type',
        'card_last4',
        'bank',
        'paid_at',
        'verified_at',
        'paystack_response',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'words_purchased' => 'integer',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'paystack_response' => 'array',
        'metadata' => 'array',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_REFUNDED = 'refunded';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wordPackage(): BelongsTo
    {
        return $this->belongsTo(WordPackage::class, 'package_id');
    }

    public function referralEarning(): HasOne
    {
        return $this->hasOne(ReferralEarning::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get amount in Naira
     */
    public function getAmountInNairaAttribute(): float
    {
        return $this->amount / 100;
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return '₦'.number_format($this->amount_in_naira, 0);
    }

    /**
     * Check if payment is successful
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if payment is pending
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Get payment method display string
     */
    public function getPaymentMethodAttribute(): string
    {
        if ($this->channel === 'card' && $this->card_type && $this->card_last4) {
            return ucfirst($this->card_type).' •••• '.$this->card_last4;
        }

        if ($this->channel === 'bank_transfer' && $this->bank) {
            return 'Bank Transfer ('.$this->bank.')';
        }

        return ucfirst($this->channel ?? 'Unknown');
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Mark payment as successful and credit words
     */
    public function markAsSuccess(array $paystackData): void
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'paystack_transaction_id' => $paystackData['id'] ?? null,
            'channel' => $paystackData['channel'] ?? null,
            'card_type' => $paystackData['authorization']['card_type'] ?? null,
            'card_last4' => $paystackData['authorization']['last4'] ?? null,
            'bank' => $paystackData['authorization']['bank'] ?? null,
            'paid_at' => isset($paystackData['paid_at']) ? \Carbon\Carbon::parse($paystackData['paid_at']) : now(),
            'verified_at' => now(),
            'paystack_response' => $paystackData,
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(?array $paystackData = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'paystack_response' => $paystackData,
        ]);
    }

    /**
     * Mark payment as abandoned
     */
    public function markAsAbandoned(): void
    {
        $this->update([
            'status' => self::STATUS_ABANDONED,
        ]);
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Generate a unique payment reference
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'PAY_'.strtoupper(Str::random(16));
        } while (self::where('paystack_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Find payment by Paystack reference
     */
    public static function findByReference(string $reference): ?self
    {
        return self::where('paystack_reference', $reference)->first();
    }

    /**
     * Create a new pending payment
     */
    public static function createPending(
        User $user,
        WordPackage $package,
        string $reference
    ): self {
        return self::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'currency' => $package->currency,
            'words_purchased' => $package->words,
            'paystack_reference' => $reference,
            'status' => self::STATUS_PENDING,
        ]);
    }
}
