<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'signup_bonus_words',
                'value' => ['amount' => 500],
                'type' => 'integer',
                'group' => 'growth',
                'description' => 'Words credited to new users on signup',
            ],
            [
                'key' => 'support_email',
                'value' => ['address' => 'support@example.com'],
                'type' => 'string',
                'group' => 'communication',
                'description' => 'Support contact email for admin notifications and user help',
            ],
            [
                'key' => 'refund_policy',
                'value' => ['allow_paystack_refunds' => true],
                'type' => 'json',
                'group' => 'billing',
                'description' => 'Refund settings for Paystack flows',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
