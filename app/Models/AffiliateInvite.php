<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateInvite extends Model
{
    public const TYPE_SINGLE_USE = 'single_use';

    public const TYPE_REUSABLE = 'reusable';

    protected $fillable = [
        'code',
        'created_by',
        'type',
        'max_uses',
        'uses',
        'expires_at',
        'is_active',
        'note',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'uses' => 'integer',
        'max_uses' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(AffiliateInviteRedemption::class, 'invite_id');
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->canBeUsed();
    }

    public function canBeUsed(): bool
    {
        if ($this->type === self::TYPE_SINGLE_USE) {
            return $this->uses < 1;
        }

        if ($this->max_uses === null) {
            return true;
        }

        return $this->uses < $this->max_uses;
    }

    public function markUsed(): void
    {
        $this->increment('uses');
    }
}
