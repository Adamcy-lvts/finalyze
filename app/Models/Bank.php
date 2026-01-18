<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = [
        'paystack_id',
        'name',
        'slug',
        'code',
        'longcode',
        'gateway',
        'pay_with_bank',
        'pay_with_bank_transfer',
        'active',
        'is_deleted',
        'country',
        'currency',
        'type',
        'nip_sort_code',
    ];

    protected $casts = [
        'pay_with_bank' => 'boolean',
        'pay_with_bank_transfer' => 'boolean',
        'active' => 'boolean',
        'is_deleted' => 'boolean',
        'paystack_id' => 'integer',
    ];
}
