<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'words',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'words' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_BONUS = 'bonus';

    public const TYPE_USAGE = 'usage';

    public const TYPE_REFUND = 'refund';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_EXPIRY = 'expiry';

    // Reference types
    public const REF_PAYMENT = 'payment';

    public const REF_CHAPTER = 'chapter';

    public const REF_GENERATION = 'generation';

    public const REF_CHAT = 'chat';

    public const REF_SUGGESTION = 'suggestion';

    public const REF_DEFENSE = 'defense';

    public const REF_SIGNUP = 'signup';

    public const REF_REFERRAL = 'referral';

    public const REF_ADMIN = 'admin';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCredits($query)
    {
        return $query->where('words', '>', 0);
    }

    public function scopeDebits($query)
    {
        return $query->where('words', '<', 0);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Check if this is a credit (positive words)
     */
    public function getIsCreditAttribute(): bool
    {
        return $this->words > 0;
    }

    /**
     * Check if this is a debit (negative words)
     */
    public function getIsDebitAttribute(): bool
    {
        return $this->words < 0;
    }

    /**
     * Get absolute word count
     */
    public function getAbsoluteWordsAttribute(): int
    {
        return abs($this->words);
    }

    /**
     * Get formatted word change
     */
    public function getFormattedWordsAttribute(): string
    {
        $prefix = $this->words > 0 ? '+' : '';

        return $prefix.number_format($this->words);
    }

    /**
     * Get icon based on transaction type
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_PURCHASE => '💳',
            self::TYPE_BONUS => '🎁',
            self::TYPE_USAGE => '📝',
            self::TYPE_REFUND => '↩️',
            self::TYPE_ADJUSTMENT => '⚙️',
            self::TYPE_EXPIRY => '⏰',
            default => '📋',
        };
    }

    /**
     * Get human-readable type
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_PURCHASE => 'Purchase',
            self::TYPE_BONUS => 'Bonus',
            self::TYPE_USAGE => 'Usage',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_EXPIRY => 'Expired',
            default => 'Unknown',
        };
    }

    // =========================================================================
    // STATIC FACTORY METHODS
    // =========================================================================

    /**
     * Record a purchase transaction
     */
    public static function recordPurchase(
        User $user,
        int $words,
        Payment $payment,
        string $description
    ): self {
        return self::create([
            'user_id' => $user->id,
            'type' => self::TYPE_PURCHASE,
            'words' => $words,
            'balance_after' => $user->word_balance,
            'description' => $description,
            'reference_type' => self::REF_PAYMENT,
            'reference_id' => $payment->id,
        ]);
    }

    /**
     * Record a bonus transaction
     */
    public static function recordBonus(
        User $user,
        int $words,
        string $description,
        string $referenceType = self::REF_SIGNUP,
        ?int $referenceId = null
    ): self {
        return self::create([
            'user_id' => $user->id,
            'type' => self::TYPE_BONUS,
            'words' => $words,
            'balance_after' => $user->word_balance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Record a usage transaction
     */
    public static function recordUsage(
        User $user,
        int $wordsUsed,
        string $description,
        string $referenceType,
        ?int $referenceId = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'user_id' => $user->id,
            'type' => self::TYPE_USAGE,
            'words' => -abs($wordsUsed), // Always negative
            'balance_after' => $user->word_balance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record a refund transaction
     */
    public static function recordRefund(
        User $user,
        int $words,
        string $description,
        string $referenceType,
        ?int $referenceId = null
    ): self {
        return self::create([
            'user_id' => $user->id,
            'type' => self::TYPE_REFUND,
            'words' => abs($words), // Always positive
            'balance_after' => $user->word_balance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Record an admin adjustment
     */
    public static function recordAdjustment(
        User $user,
        int $words,
        string $description,
        ?int $adminId = null
    ): self {
        return self::create([
            'user_id' => $user->id,
            'type' => self::TYPE_ADJUSTMENT,
            'words' => $words,
            'balance_after' => $user->word_balance,
            'description' => $description,
            'reference_type' => self::REF_ADMIN,
            'reference_id' => $adminId,
        ]);
    }
}
