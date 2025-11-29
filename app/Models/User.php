<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, Impersonate;

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
        $totalAllocated = $this->total_words_purchased + $this->bonus_words_received;
        $percentageUsed = $totalAllocated > 0
            ? round(($this->total_words_used / $totalAllocated) * 100, 1)
            : 0;

        return [
            'balance' => $this->word_balance,
            'formatted_balance' => number_format($this->word_balance),
            'total_purchased' => $this->total_words_purchased,
            'total_used' => $this->total_words_used,
            'bonus_received' => $this->bonus_words_received,
            'total_allocated' => $totalAllocated,
            'percentage_used' => $percentageUsed,
            'percentage_remaining' => 100 - $percentageUsed,
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
}
