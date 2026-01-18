<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateInviteRedemption extends Model
{
    protected $fillable = [
        'invite_id',
        'user_id',
        'ip',
        'user_agent',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function invite(): BelongsTo
    {
        return $this->belongsTo(AffiliateInvite::class, 'invite_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
