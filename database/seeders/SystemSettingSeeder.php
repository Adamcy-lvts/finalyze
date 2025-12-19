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
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
