<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenAICreditSetting extends Model
{
    protected $table = 'openai_credit_settings';

    protected $fillable = [
        'initial_balance',
        'balance_set_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'float',
            'balance_set_at' => 'datetime',
        ];
    }

    /**
     * Get the current settings (singleton pattern).
     */
    public static function current(): self
    {
        return self::firstOrCreate([], [
            'initial_balance' => config('ai.manual_credit_balance', 0),
            'balance_set_at' => now(),
        ]);
    }

    /**
     * Update the initial balance.
     */
    public function setBalance(float $balance, ?string $notes = null): self
    {
        $this->update([
            'initial_balance' => $balance,
            'balance_set_at' => now(),
            'notes' => $notes,
        ]);

        return $this;
    }
}
