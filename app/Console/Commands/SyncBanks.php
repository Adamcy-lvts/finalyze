<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Services\PaystackService;
use Illuminate\Console\Command;

class SyncBanks extends Command
{
    protected $signature = 'banks:sync {--country=nigeria} {--truncate}';

    protected $description = 'Sync Paystack banks into the local database';

    public function handle(PaystackService $paystackService): int
    {
        $country = $this->option('country') ?? 'nigeria';

        if ($this->option('truncate')) {
            $this->info('Truncating banks table...');
            Bank::query()->truncate();
        }

        $this->info('Fetching banks from Paystack...');
        $banks = $paystackService->fetchBanksFromApi([
            'country' => $country,
            'use_cursor' => true,
            'perPage' => 100,
        ]);

        if (empty($banks)) {
            $this->error('No banks returned from Paystack.');
            return self::FAILURE;
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

        $this->info('Banks synced: '.count($payload));

        return self::SUCCESS;
    }
}
