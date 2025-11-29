<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [
                'key' => 'signup_enabled',
                'name' => 'User Registration',
                'description' => 'Toggle user signups',
                'is_enabled' => true,
            ],
            [
                'key' => 'ai_generation_enabled',
                'name' => 'AI Generation',
                'description' => 'Enable AI content generation',
                'is_enabled' => true,
            ],
            [
                'key' => 'payments_enabled',
                'name' => 'Payments',
                'description' => 'Enable payment processing',
                'is_enabled' => true,
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::updateOrCreate(['key' => $flag['key']], $flag);
        }
    }
}
