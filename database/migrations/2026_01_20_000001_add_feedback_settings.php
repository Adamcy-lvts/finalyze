<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $settings = [
            [
                'key' => 'auth.require_email_verification',
                'value' => json_encode(true),
                'type' => 'boolean',
                'group' => 'auth',
                'description' => 'Require users to verify email before accessing the app.',
            ],
            [
                'key' => 'feedback.minimum_account_age_days',
                'value' => json_encode(10),
                'type' => 'integer',
                'group' => 'feedback',
                'description' => 'Minimum account age (days) before showing feedback prompt.',
            ],
            [
                'key' => 'feedback.minimum_words_used',
                'value' => json_encode(7000),
                'type' => 'integer',
                'group' => 'feedback',
                'description' => 'Minimum words used before showing feedback prompt.',
            ],
            [
                'key' => 'feedback.cooldown_hours',
                'value' => json_encode(72),
                'type' => 'integer',
                'group' => 'feedback',
                'description' => 'Cooldown in hours between feedback prompts.',
            ],
            [
                'key' => 'feedback.max_prompt_shows',
                'value' => json_encode(3),
                'type' => 'integer',
                'group' => 'feedback',
                'description' => 'Maximum number of feedback prompts per user.',
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('system_settings')->where('key', $setting['key'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('system_settings')->insert([
                'key' => $setting['key'],
                'value' => $setting['value'],
                'type' => $setting['type'],
                'group' => $setting['group'],
                'description' => $setting['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->whereIn('key', [
            'auth.require_email_verification',
            'feedback.minimum_account_age_days',
            'feedback.minimum_words_used',
            'feedback.cooldown_hours',
            'feedback.max_prompt_shows',
        ])->delete();
    }
};
