<?php

namespace Database\Seeders;

use App\Services\PaystackService;
use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(PaystackService $paystackService): void
    {
        $banks = $paystackService->fetchBanksFromApi([
            'country' => 'nigeria',
            'use_cursor' => true,
            'perPage' => 100,
        ]);

        if (empty($banks)) {
            return;
        }

        $payload = collect($banks)->map(function ($bank) {
            return [
                'paystack_id' => $bank['id'] ?? null,
                'name' => $bank['name'] ?? null,
                'slug' => $bank['slug'] ?? null,
                'code' => $bank['code'] ?? null,
                'longcode' => $bank['longcode'] ?? null,
                'gateway' => $bank['gateway'] ?? null,
                'pay_with_bank' => (bool) ($bank['pay_with_bank'] ?? false),
                'pay_with_bank_transfer' => (bool) ($bank['pay_with_bank_transfer'] ?? false),
                'active' => (bool) ($bank['active'] ?? true),
                'is_deleted' => (bool) ($bank['is_deleted'] ?? false),
                'country' => $bank['country'] ?? null,
                'currency' => $bank['currency'] ?? null,
                'type' => $bank['type'] ?? null,
                'nip_sort_code' => $bank['nip_sort_code'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->filter(fn ($row) => $row['paystack_id'] !== null)->values()->all();

        Bank::upsert(
            $payload,
            ['paystack_id'],
            [
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
                'updated_at',
            ]
        );
    }
}
