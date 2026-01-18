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
            [
                'key' => 'ai.global_system_prompt',
                'value' => 'You are a helpful, careful academic AI assistant. Follow the user instructions and maintain a formal academic tone. Prefer the bullet symbol (•) for unordered lists unless explicitly requested otherwise. Never use "&" — always write "and".',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'Global system prompt applied to most AI features (chapter writing, chat, etc.).',
            ],
            [
                'key' => 'ai.chat_system_prompt',
                'value' => 'You are an academic writing assistant helping a student with their research project. Be supportive, specific, and actionable. Keep responses concise unless the user requests more detail.',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'Chat-specific system prompt (merged with the global system prompt).',
            ],
            [
                'key' => 'ai.editor_system_prompt',
                'value' => 'You are an expert academic editor. Follow the user instructions precisely. Preserve meaning, improve clarity, and maintain an academic tone appropriate to the provided context. Output only what the user asked for (no extra commentary).',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'Editor system prompt used for rephrase/expand/improve actions (merged with the global system prompt).',
            ],
            [
                'key' => 'ai.analysis_system_prompt',
                'value' => 'You are an expert academic writing evaluator. Return exactly the requested JSON format with no additional commentary, markdown, or code fences.',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'Analysis system prompt used for scoring/evaluation (merged with the global system prompt).',
            ],
            // Referral system settings
            [
                'key' => 'referral.enabled',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'group' => 'referral',
                'description' => 'Enable or disable the referral system',
            ],
            [
                'key' => 'referral.commission_percentage',
                'value' => ['percentage' => 10],
                'type' => 'integer',
                'group' => 'referral',
                'description' => 'Default commission percentage for referrers (e.g., 10 = 10%)',
            ],
            [
                'key' => 'referral.minimum_payment_amount',
                'value' => ['amount' => 100000],
                'type' => 'integer',
                'group' => 'referral',
                'description' => 'Minimum payment amount in kobo to qualify for referral commission (default: 1000 NGN)',
            ],
            [
                'key' => 'referral.fee_bearer',
                'value' => ['bearer' => 'account'],
                'type' => 'string',
                'group' => 'referral',
                'description' => 'Who pays Paystack transaction fees (account, subaccount, all, all-proportional)',
            ],
            // Affiliate system settings
            [
                'key' => 'affiliate.enabled',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'group' => 'affiliate',
                'description' => 'Enable or disable the affiliate system',
            ],
            [
                'key' => 'affiliate.registration_open',
                'value' => ['enabled' => false],
                'type' => 'boolean',
                'group' => 'affiliate',
                'description' => 'Allow public affiliate registration',
            ],
            [
                'key' => 'affiliate.commission_percentage',
                'value' => ['percentage' => 10],
                'type' => 'integer',
                'group' => 'affiliate',
                'description' => 'Default commission percentage for affiliates',
            ],
            [
                'key' => 'affiliate.minimum_payment_amount',
                'value' => ['amount' => 100000],
                'type' => 'integer',
                'group' => 'affiliate',
                'description' => 'Minimum payment amount in kobo to qualify for affiliate commission',
            ],
            [
                'key' => 'affiliate.fee_bearer',
                'value' => ['bearer' => 'account'],
                'type' => 'string',
                'group' => 'affiliate',
                'description' => 'Who pays Paystack transaction fees for affiliate split payments',
            ],
            [
                'key' => 'affiliate.promo_popup_enabled',
                'value' => ['enabled' => true],
                'type' => 'boolean',
                'group' => 'affiliate',
                'description' => 'Show affiliate promo popup to regular users',
            ],
            [
                'key' => 'affiliate.promo_popup_delay_days',
                'value' => ['days' => 7],
                'type' => 'integer',
                'group' => 'affiliate',
                'description' => 'Days after signup before showing affiliate promo popup',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
