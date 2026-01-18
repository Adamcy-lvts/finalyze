<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralBankAccount extends Model
{
    protected $fillable = [
        'user_id',
        'bank_code',
        'bank_name',
        'account_number',
        'account_name',
        'subaccount_code',
        'is_verified',
        'verified_at',
        'is_active',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Hide sensitive data from serialization
     */
    protected $hidden = [
        'account_number',
    ];

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get masked account number for display (shows last 4 digits)
     */
    public function getMaskedAccountNumberAttribute(): string
    {
        return '******'.substr($this->account_number, -4);
    }

    /**
     * Get full account number (for internal use only)
     */
    public function getFullAccountNumberAttribute(): string
    {
        return $this->account_number;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Deactivate this bank account
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }
}
