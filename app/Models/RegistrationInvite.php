<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RegistrationInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'max_uses',
        'uses',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'uses' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RegistrationInviteRedemption::class, 'invite_id');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses');
            });
    }

    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function status(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->max_uses !== null && $this->uses >= $this->max_uses) {
            return 'used_up';
        }

        return 'active';
    }

    public static function generateCode(int $length = 10): string
    {
        // Unambiguous Base32-ish alphabet (no 0/O/I/1).
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    public static function createUnique(array $attributes = []): self
    {
        $tries = 0;
        do {
            $attributes['code'] = $attributes['code'] ?? self::generateCode();
            $exists = self::query()->where('code', $attributes['code'])->exists();
            $tries++;
            if ($exists) {
                unset($attributes['code']);
            }
        } while ($exists && $tries < 10);

        if ($exists) {
            // Extremely unlikely unless the code space is too small.
            $attributes['code'] = Str::upper(Str::random(20));
        }

        return self::create($attributes);
    }
}

