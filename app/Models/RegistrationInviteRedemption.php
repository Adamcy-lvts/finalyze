<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationInviteRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'invite_id',
        'user_id',
        'email',
        'ip',
        'user_agent',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'invite_id' => 'integer',
            'user_id' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    public function invite(): BelongsTo
    {
        return $this->belongsTo(RegistrationInvite::class, 'invite_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

